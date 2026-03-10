<?php

namespace App\Jobs;

use App\Enums\JobStatus;
use App\Models\VideoClip;
use App\Services\CaptionGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class ExtractVideoClipVerticalVideo implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    public function __construct(
        public VideoClip $videoClip
    ) {
        $this->onQueue('video-processing');
    }

    public function uniqueId(): int
    {
        return $this->videoClip->id;
    }

    /**
     * @return list<WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            new WithoutOverlapping('extract-clip-'.$this->videoClip->id),
        ];
    }

    public function handle(CaptionGenerator $captionGenerator): void
    {
        $videoClip = $this->videoClip;
        $video = $videoClip->video;
        $assPath = null;

        try {
            $videoClip->update([
                'clip_video_status' => JobStatus::Processing,
                'clip_video_error' => null,
                'clip_video_started_at' => now(),
                'clip_video_completed_at' => null,
            ]);
            if ($video->vertical_video_status !== JobStatus::Completed || $video->vertical_video_path === null) {
                throw new \RuntimeException('Video does not have a completed vertical video');
            }

            $videoClip->refresh();
            $startTime = $videoClip->starts_at;
            $endTime = $videoClip->ends_at;
            $duration = $videoClip->duration;

            // Build the output path: clips/YYYY-MM-DD_HHMM_MMSS.mp4
            $videoDate = $video->date->timezone('America/Chicago');
            $clipStartSeconds = (int) floor($startTime);
            $outputRelativePath = sprintf(
                'clips/%s_%02d%02d.mp4',
                $videoDate->format('Y-m-d_Hi'),
                intdiv($clipStartSeconds, 60),
                $clipStartSeconds % 60,
            );

            // Delete the previous clip file if the path changed (e.g., timing was adjusted)
            $oldPath = $videoClip->clip_video_path;
            if ($oldPath !== null && $oldPath !== $outputRelativePath) {
                Storage::disk('public')->delete($oldPath);
            }

            // Generate ASS captions from transcript word-level data
            $segments = $video->transcript['segments'] ?? [];
            $clipSegments = array_slice(
                $segments,
                $videoClip->start_segment_index,
                $videoClip->end_segment_index - $videoClip->start_segment_index + 1
            );
            $assContent = $captionGenerator->generateAss($clipSegments, $startTime, $endTime);
            $assPath = tempnam(sys_get_temp_dir(), 'caption_').'.ass';
            file_put_contents($assPath, $assContent);

            $inputDisk = Storage::disk('public');
            $inputPath = $inputDisk->path($video->vertical_video_path);

            $outputDisk = Storage::disk('public');
            $outputAbsolutePath = $outputDisk->path($outputRelativePath);

            $outputDir = dirname($outputAbsolutePath);
            if (! is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $fontsDir = resource_path('fonts');

            $result = Process::timeout(300)->run([
                'ffmpeg',
                '-ss', (string) $startTime,
                '-i', $inputPath,
                '-t', (string) $duration,
                '-vf', "ass={$assPath}:fontsdir={$fontsDir},setpts=PTS-STARTPTS",
                '-af', 'asetpts=PTS-STARTPTS',
                '-c:v', 'libx264',
                '-preset', 'medium',
                '-crf', '23',
                '-pix_fmt', 'yuv420p',
                '-c:a', 'aac',
                '-b:a', '128k',
                '-movflags', '+faststart',
                '-y', $outputAbsolutePath,
            ]);

            if ($result->failed()) {
                throw new \RuntimeException($result->errorOutput() ?: $result->output());
            }

            $videoClip->update([
                'clip_video_status' => JobStatus::Completed,
                'clip_video_path' => $outputRelativePath,
                'clip_video_completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Video clip vertical video extraction failed', [
                'video_clip_id' => $videoClip->id,
                'video_id' => $video->id,
                'exception' => $e,
            ]);

            $videoClip->updateQuietly([
                'clip_video_status' => JobStatus::Failed,
                'clip_video_error' => $e->getMessage(),
            ]);
        } finally {
            if ($assPath !== null && file_exists($assPath)) {
                unlink($assPath);
            }
        }
    }
}
