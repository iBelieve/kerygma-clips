<?php

namespace App\Jobs;

use App\Enums\JobStatus;
use App\Models\VideoClip;
use App\Services\YouTubeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class UpdateYouTubeSchedule implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 60, 120];

    public function __construct(
        public VideoClip $clip,
        public string $scheduledDate,
    ) {}

    public function handle(YouTubeService $youtube): void
    {
        if ($this->clip->youtube_video_id === null) {
            return;
        }

        $youtube->updateSchedule($this->clip->youtube_video_id, $this->scheduledDate);

        $this->clip->update([
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
