<?php

namespace App\Jobs;

use App\Enums\JobStatus;
use App\Models\Video;
use App\Services\VideoProbe;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\TimeoutExceededException;
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
        public Video $video
    ) {
        $this->onQueue('video-processing');
    }

    public function uniqueId(): int
    {
        return $this->video->id;
    }

    public function handle(VideoProbe $videoProbe): void
    {
        $this->video->update([
            'vertical_video_status' => JobStatus::Processing,
            'vertical_video_error' => null,
            'vertical_video_started_at' => now(),
            'vertical_video_completed_at' => null,
        ]);

        $inputDisk = $this->video->rawVideoDisk();
        $outputDisk = Storage::disk('public');
        $absolutePath = $inputDisk->path($this->video->raw_video_path);

        try {
            $metadata = $videoProbe->getVideoMetadata($absolutePath);

            if ($metadata === null) {
                throw new \RuntimeException(
                    'Failed to detect video dimensions via ffprobe'
                );
            }

            $this->video->update($metadata);

            $sourceWidth = $metadata['source_width'];
            $sourceHeight = $metadata['source_height'];

            $isAlreadyVertical = $metadata['is_source_vertical'];
            $isAlreadyTargetSize = $sourceWidth === 1080 && $sourceHeight === 1920;

            if (! $isAlreadyVertical) {
                $cropHeight = $sourceHeight;
                $cropWidth = min((int) round($sourceHeight * 9 / 16), $sourceWidth);

                $cropCenter = $this->video->vertical_video_crop_center ?? 50;
                $centerX = (int) round($sourceWidth * $cropCenter / 100);
                $cropX = max(0, min($centerX - intdiv($cropWidth, 2), $sourceWidth - $cropWidth));

                $videoFilter = "crop={$cropWidth}:{$cropHeight}:{$cropX}:0,scale=1080:1920";
            } elseif (! $isAlreadyTargetSize) {
                $videoFilter = 'scale=1080:1920';
            } else {
                $videoFilter = null;
            }

            $inputFilename = pathinfo($this->video->raw_video_path, PATHINFO_FILENAME);
            $outputRelativePath = "vertical/{$inputFilename}.mp4";
            $outputAbsolutePath = $outputDisk->path($outputRelativePath);

            // Ensure the vertical/ directory exists
            $outputDir = dirname($outputAbsolutePath);
            if (! is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $command = ['ffmpeg', '-i', $absolutePath];

            if ($videoFilter !== null) {
                array_push($command,
                    '-vf', $videoFilter,
                    '-c:v', 'libx264',
                    '-preset', 'medium',
                    '-crf', '23',
                    '-c:a', 'aac',
                    '-b:a', '128k',
                    '-force_key_frames', 'expr:gte(t,n_forced*1)',
                );
            } else {
                array_push($command, '-c', 'copy');
            }

            array_push($command, '-movflags', '+faststart', '-y', $outputAbsolutePath);

            $result = Process::timeout(7200)->run($command);

            if ($result->failed()) {
                throw new \RuntimeException($result->errorOutput() ?: $result->output());
            }

            $this->video->update([
                'vertical_video_status' => JobStatus::Completed,
                'vertical_video_path' => $outputRelativePath,
                'vertical_video_completed_at' => now(),
            ]);

            foreach ($this->video->videoClips as $clip) {
                ExtractVideoClipVerticalVideo::dispatch($clip);
            }
        } catch (\Throwable $e) {
            $isTimeout = $e instanceof ProcessTimedOutException;

            Log::error('Vertical video conversion failed', [
                'video_path' => $this->video->raw_video_path,
                'exception' => $e,
            ]);

            $this->video->update([
                'vertical_video_status' => $isTimeout ? JobStatus::TimedOut : JobStatus::Failed,
                'vertical_video_error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        if ($this->video->vertical_video_status !== JobStatus::Processing) {
            return;
        }

        $isTimeout = $exception instanceof TimeoutExceededException
            || $exception instanceof ProcessTimedOutException
            || $exception instanceof \Symfony\Component\Process\Exception\ProcessTimedOutException;

        $this->video->update([
            'vertical_video_status' => $isTimeout ? JobStatus::TimedOut : JobStatus::Failed,
            'vertical_video_error' => $exception->getMessage(),
        ]);
    }
}
