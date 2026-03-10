<?php

namespace App\Console\Commands;

use App\Models\Video;
use Illuminate\Console\Command;

class ListVideos extends Command
{
    protected $signature = 'app:list-videos';

    protected $description = 'List all videos and their transcript status';

    public function handle(): int
    {
        $videos = Video::orderBy('date', 'desc')->get();

        if ($videos->isEmpty()) {
            $this->info('No videos found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Date', 'File', 'Transcript Status'],
            $videos->map(fn (Video $video) => [
                $video->id,
                $video->date->timezone('America/Chicago')->format('Y-m-d H:i'),
                $video->raw_video_path,
                $video->transcript_status->value,
            ]),
        );

        return self::SUCCESS;
    }
}
