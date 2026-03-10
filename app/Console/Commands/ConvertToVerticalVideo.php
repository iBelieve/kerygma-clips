<?php

namespace App\Console\Commands;

use App\Enums\JobStatus;
use App\Jobs\ConvertToVerticalVideo as ConvertToVerticalVideoJob;
use App\Models\Video;
use Illuminate\Console\Command;

class ConvertToVerticalVideo extends Command
{
    protected $signature = 'app:convert-to-vertical-video
                            {id : The ID of the video to convert}';

    protected $description = 'Convert a video to vertical (9:16) format';

    public function handle(): int
    {
        $video = Video::find($this->argument('id'));

        if ($video === null) {
            $this->error("Video with ID {$this->argument('id')} not found.");

            return self::FAILURE;
        }

        if ($video->vertical_video_status === JobStatus::Processing) {
            $this->error("Video #{$video->id} is already being converted.");

            return self::FAILURE;
        }

        $this->info("Converting video #{$video->id} to vertical format...");
        ConvertToVerticalVideoJob::dispatchSync($video);

        $video->refresh();

        if ($video->vertical_video_status === JobStatus::Completed) {
            $this->info('Vertical video conversion completed successfully.');
        } else {
            $this->error("Conversion failed: {$video->vertical_video_error}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
