<?php

namespace App\Jobs;

use App\Enums\JobStatus;
use App\Models\SermonClip;
use App\Services\SubtitleGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class ExtractSermonClipVerticalVideo implements ShouldQueue
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
        public SermonClip $sermonClip
    ) {
        $this->onQueue('video-processing');
    }

    public function handle(SubtitleGenerator $subtitleGenerator): void
    {
        $sermonClip = $this->sermonClip;
        $sermonVideo = $sermonClip->sermonVideo;
        $assFilePath = null;

        try {
            $sermonClip->update([
                'clip_video_status' => JobStatus::Processing,
                'clip_video_error' => null,
                'clip_video_started_at' => now(),
                'clip_video_completed_at' => null,
            ]);
            if ($sermonVideo->vertical_video_status !== JobStatus::Completed || $sermonVideo->vertical_video_path === null) {
                throw new \RuntimeException('Sermon video does not have a completed vertical video');
            }

            $segments = $sermonVideo->transcript['segments'] ?? [];

            if (! isset($segments[$sermonClip->start_segment_index], $segments[$sermonClip->end_segment_index])) {
                throw new \RuntimeException('Clip segment indices are out of bounds');
            }

            $startTime = (float) $segments[$sermonClip->start_segment_index]['start'];
            $endTime = (float) $segments[$sermonClip->end_segment_index]['end'];
            $duration = $endTime - $startTime;

            $inputDisk = Storage::disk('public');
            $inputPath = $inputDisk->path($sermonVideo->vertical_video_path);

            $outputDisk = Storage::disk('public');
            $outputRelativePath = "clips/{$sermonClip->id}.mp4";
            $outputAbsolutePath = $outputDisk->path($outputRelativePath);

            $outputDir = dirname($outputAbsolutePath);
            if (! is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $assContent = $subtitleGenerator->generateAssContent(
                $sermonVideo->transcript,
                $sermonClip->start_segment_index,
                $sermonClip->end_segment_index,
                $startTime,
            );

            if ($assContent !== null) {
                $assFilePath = sys_get_temp_dir()."/sermon_clip_{$sermonClip->id}.ass";
                file_put_contents($assFilePath, $assContent);
            }

            $ffmpegCommand = [
                'ffmpeg',
                '-ss', (string) $startTime,
                '-i', $inputPath,
                '-t', (string) $duration,
            ];

            if ($assFilePath !== null) {
                $ffmpegCommand = [...$ffmpegCommand, '-vf', "ass={$assFilePath}"];
            }

            $ffmpegCommand = [
                ...$ffmpegCommand,
                '-c:v', 'libx264',
                '-preset', 'medium',
                '-crf', '23',
                '-c:a', 'aac',
                '-b:a', '128k',
                '-movflags', '+faststart',
                '-y', $outputAbsolutePath,
            ];

            $result = Process::timeout(300)->run($ffmpegCommand);

            if ($result->failed()) {
                throw new \RuntimeException($result->errorOutput() ?: $result->output());
            }

            $sermonClip->update([
                'clip_video_status' => JobStatus::Completed,
                'clip_video_path' => $outputRelativePath,
                'clip_video_completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Sermon clip vertical video extraction failed', [
                'sermon_clip_id' => $sermonClip->id,
                'sermon_video_id' => $sermonVideo->id,
                'exception' => $e,
            ]);

            $sermonClip->updateQuietly([
                'clip_video_status' => JobStatus::Failed,
                'clip_video_error' => $e->getMessage(),
            ]);
        } finally {
            if ($assFilePath !== null && file_exists($assFilePath)) {
                unlink($assFilePath);
            }
        }
    }
}
