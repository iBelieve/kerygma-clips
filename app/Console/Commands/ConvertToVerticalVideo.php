<?php

namespace App\Console\Commands;

use App\Enums\JobStatus;
use App\Jobs\ConvertToVerticalVideo as ConvertToVerticalVideoJob;
use App\Models\SermonVideo;
use Illuminate\Console\Command;

class ConvertToVerticalVideo extends Command
{
    protected $signature = 'app:convert-to-vertical-video
                            {id : The ID of the sermon video to convert}';

    protected $description = 'Convert a sermon video to vertical (9:16) format';

    public function handle(): int
    {
        $sermonVideo = SermonVideo::find($this->argument('id'));

        if ($sermonVideo === null) {
            $this->error("Sermon video with ID {$this->argument('id')} not found.");

            return self::FAILURE;
        }

        if ($sermonVideo->vertical_video_status === JobStatus::Processing) {
            $this->error("Sermon video #{$sermonVideo->id} is already being converted.");

            return self::FAILURE;
        }

        $this->info("Converting sermon video #{$sermonVideo->id} to vertical format...");
        ConvertToVerticalVideoJob::dispatchSync($sermonVideo);

        $sermonVideo->refresh();

        if ($sermonVideo->vertical_video_status === JobStatus::Completed) {
            $this->info('Vertical video conversion completed successfully.');
        } else {
            $this->error("Conversion failed: {$sermonVideo->vertical_video_error}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
