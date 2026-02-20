<?php

namespace App\Console\Commands;

use App\Enums\TranscriptStatus;
use App\Jobs\TranscribeSermonVideo as TranscribeSermonVideoJob;
use App\Models\SermonVideo;
use Illuminate\Console\Command;

class TranscribeSermonVideo extends Command
{
    protected $signature = 'app:transcribe-sermon-video
                            {id : The ID of the sermon video to transcribe}';

    protected $description = 'Run transcription for a sermon video';

    public function handle(): int
    {
        $sermonVideo = SermonVideo::find($this->argument('id'));

        if ($sermonVideo === null) {
            $this->error("Sermon video with ID {$this->argument('id')} not found.");

            return self::FAILURE;
        }

        if ($sermonVideo->transcript_status === TranscriptStatus::Processing) {
            $this->error("Sermon video #{$sermonVideo->id} is already being processed.");

            return self::FAILURE;
        }

        $this->info("Running transcription for sermon video #{$sermonVideo->id}...");
        TranscribeSermonVideoJob::dispatchSync($sermonVideo);

        $sermonVideo->refresh();

        if ($sermonVideo->transcript_status === TranscriptStatus::Completed) {
            $this->info('Transcription completed successfully.');
        } else {
            $this->error("Transcription failed: {$sermonVideo->transcript_error}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
