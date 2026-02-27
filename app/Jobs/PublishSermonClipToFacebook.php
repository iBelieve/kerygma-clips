<?php

namespace App\Jobs;

use App\Enums\JobStatus;
use App\Models\SermonClip;
use App\Services\FacebookReelsService;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PublishSermonClipToFacebook implements ShouldQueue
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
        public SermonClip $sermonClip
    ) {}

    public function handle(FacebookReelsService $facebook): void
    {
        $sermonClip = $this->sermonClip;

        try {
            $sermonClip->update([
                'fb_reel_status' => JobStatus::Processing,
                'fb_reel_error' => null,
                'fb_reel_started_at' => now(),
                'fb_reel_completed_at' => null,
            ]);

            if ($sermonClip->clip_video_status !== JobStatus::Completed || $sermonClip->clip_video_path === null) {
                throw new \RuntimeException('Clip video is not ready for publishing');
            }

            $disk = Storage::disk('public');
            $filePath = $disk->path($sermonClip->clip_video_path);

            if (! file_exists($filePath)) {
                throw new \RuntimeException("Clip video file not found: {$sermonClip->clip_video_path}");
            }

            $description = $sermonClip->fb_reel_description ?? $sermonClip->title ?? '';

            $scheduledTimestamp = $sermonClip->fb_reel_scheduled_for?->getTimestamp();

            $videoId = $facebook->initialize();
            $facebook->upload($videoId, $filePath);
            $facebook->publish($videoId, $description, $scheduledTimestamp);

            $sermonClip->update([
                'fb_reel_status' => JobStatus::Completed,
                'fb_reel_id' => $videoId,
                'fb_reel_completed_at' => now(),
                'fb_reel_published_at' => $scheduledTimestamp === null ? CarbonImmutable::now() : null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Facebook Reel publishing failed', [
                'sermon_clip_id' => $sermonClip->id,
                'exception' => $e,
            ]);

            $sermonClip->updateQuietly([
                'fb_reel_status' => JobStatus::Failed,
                'fb_reel_error' => $e->getMessage(),
            ]);
        }
    }
}
