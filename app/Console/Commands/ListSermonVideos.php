<?php

namespace App\Console\Commands;

use App\Models\SermonVideo;
use Illuminate\Console\Command;

class ListSermonVideos extends Command
{
    protected $signature = 'app:list-sermon-videos';

    protected $description = 'List all sermon videos and their transcript status';

    public function handle(): int
    {
        $videos = SermonVideo::orderBy('date', 'desc')->get();

        if ($videos->isEmpty()) {
            $this->info('No sermon videos found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Date', 'File', 'Transcript Status'],
            $videos->map(fn (SermonVideo $video) => [
                $video->id,
                $video->date->format('Y-m-d H:i'),
                $video->raw_video_path,
                $video->transcript_status->value,
            ]),
        );

        return self::SUCCESS;
    }
}
