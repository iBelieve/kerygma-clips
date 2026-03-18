<?php

namespace App\Jobs;

use App\Enums\JobStatus;
use App\Models\VideoClip;
use App\Services\YouTubeService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class UploadToYouTube implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 60, 120];

    public function __construct(
        public VideoClip $clip,
        public string $scheduledDate,
    ) {}

    public function uniqueId(): int
    {
        return $this->clip->id;
    }

    public function handle(YouTubeService $youtube): void
    {
        $this->clip->update([
            'youtube_status' => JobStatus::Processing,
            'youtube_error' => null,
        ]);

        $videoId = $youtube->upload($this->clip, $this->scheduledDate);

        $this->clip->update([
            'youtube_video_id' => $videoId,
            'youtube_status' => JobStatus::Completed,
            'youtube_uploaded_at' => CarbonImmutable::now(),
            'youtube_published_at' => $this->clip->scheduled_date,
        ]);
    }

    public function failed(?\Throwable $exception): void
    {
        $this->clip->update([
            'youtube_status' => JobStatus::Failed,
            'youtube_error' => $exception?->getMessage(),
        ]);
    }
}
