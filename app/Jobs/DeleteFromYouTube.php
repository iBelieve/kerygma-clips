<?php

namespace App\Jobs;

use App\Services\YouTubeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeleteFromYouTube implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 60, 120];

    public function __construct(
        public string $youtubeVideoId,
    ) {}

    public function handle(YouTubeService $youtube): void
    {
        $youtube->delete($this->youtubeVideoId);
    }
}
