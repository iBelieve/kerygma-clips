<?php

namespace App\Jobs;

use App\Enums\JobStatus;
use App\Models\VideoClip;
use App\Services\ThumbnailGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateClipThumbnail implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

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
            new WithoutOverlapping('generate-thumbnail-'.$this->videoClip->id),
        ];
    }

    public function handle(ThumbnailGenerator $thumbnailGenerator): void
    {
        $videoClip = $this->videoClip;
        $video = $videoClip->video;

        try {
            // Wait for title to be available (generated concurrently)
            if ($videoClip->title === null) {
                $this->release(10);

                return;
            }

            $videoClip->update([
                'thumbnail_status' => JobStatus::Processing,
                'thumbnail_error' => null,
                'thumbnail_started_at' => now(),
                'thumbnail_completed_at' => null,
            ]);

            if ($video->vertical_video_status !== JobStatus::Completed || $video->vertical_video_path === null) {
                throw new \RuntimeException('Video does not have a completed vertical video');
            }

            // Delete the previous thumbnail file if the path changed
            $oldPath = $videoClip->thumbnail_path;
            $newPath = $thumbnailGenerator->buildOutputPath($videoClip);
            if ($oldPath !== null && $oldPath !== $newPath) {
                Storage::disk('public')->delete($oldPath);
            }

            $outputRelativePath = $thumbnailGenerator->generate($videoClip);

            $videoClip->update([
                'thumbnail_status' => JobStatus::Completed,
                'thumbnail_path' => $outputRelativePath,
                'thumbnail_completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Clip thumbnail generation failed', [
                'video_clip_id' => $videoClip->id,
                'video_id' => $video->id,
                'exception' => $e,
            ]);

            $videoClip->updateQuietly([
                'thumbnail_status' => JobStatus::Failed,
                'thumbnail_error' => $e->getMessage(),
            ]);
        }
    }
}
