<?php

namespace App\Jobs;

use App\Enums\JobStatus;
use App\Models\SermonVideo;
use App\Services\VideoProbe;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class ConvertToVerticalVideo implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 7200;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    public function __construct(
        public SermonVideo $sermonVideo
    ) {
        $this->onQueue('video-processing');
    }

    public function uniqueId(): int
    {
        return $this->sermonVideo->id;
    }

    public function handle(VideoProbe $videoProbe): void
    {
        $this->sermonVideo->update([
            'vertical_video_status' => JobStatus::Processing,
            'vertical_video_error' => null,
            'vertical_video_started_at' => now(),
            'vertical_video_completed_at' => null,
        ]);

        $inputDisk = Storage::disk('sermon_videos');
        $outputDisk = Storage::disk('public');
        $absolutePath = $inputDisk->path($this->sermonVideo->raw_video_path);

        try {
            $dimensions = $videoProbe->getVideoDimensions($absolutePath);

            if ($dimensions === null) {
                throw new \RuntimeException(
                    'Failed to detect video dimensions via ffprobe'
                );
            }

            $sourceWidth = $dimensions['width'];
            $sourceHeight = $dimensions['height'];

            $cropHeight = $sourceHeight;
            $cropWidth = min((int) round($sourceHeight * 9 / 16), $sourceWidth);

            $cropCenter = $this->sermonVideo->vertical_video_crop_center ?? 50;
            $centerX = (int) round($sourceWidth * $cropCenter / 100);
            $cropX = max(0, min($centerX - intdiv($cropWidth, 2), $sourceWidth - $cropWidth));

            $inputFilename = pathinfo($this->sermonVideo->raw_video_path, PATHINFO_FILENAME);
            $outputRelativePath = "vertical/{$inputFilename}.mp4";
            $outputAbsolutePath = $outputDisk->path($outputRelativePath);

            // Ensure the vertical/ directory exists
            $outputDir = dirname($outputAbsolutePath);
            if (! is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $result = Process::timeout(7200)->run([
                'ffmpeg',
                '-i', $absolutePath,
                '-vf', "crop={$cropWidth}:{$cropHeight}:{$cropX}:0,scale=1080:1920",
                '-c:v', 'libx264',
                '-preset', 'medium',
                '-crf', '23',
                '-c:a', 'aac',
                '-b:a', '128k',
                '-force_key_frames', 'expr:gte(t,n_forced*1)',
                '-movflags', '+faststart',
                '-y', $outputAbsolutePath,
            ]);

            if ($result->failed()) {
                throw new \RuntimeException($result->errorOutput() ?: $result->output());
            }

            $this->sermonVideo->update([
                'vertical_video_status' => JobStatus::Completed,
                'vertical_video_path' => $outputRelativePath,
                'vertical_video_completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Vertical video conversion failed', [
                'video_path' => $this->sermonVideo->raw_video_path,
                'exception' => $e,
            ]);

            $this->sermonVideo->update([
                'vertical_video_status' => JobStatus::Failed,
                'vertical_video_error' => $e->getMessage(),
            ]);
        }
    }
}
