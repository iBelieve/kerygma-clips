<?php

namespace App\Console\Commands;

use App\Enums\TranscriptStatus;
use App\Jobs\TranscribeSermonVideo as TranscribeSermonVideoJob;
use App\Models\SermonVideo;
use Illuminate\Console\Command;

class TranscribeSermonVideo extends Command
{
    protected $signature = 'app:transcribe-sermon-video
                            {id : The ID of the sermon video to transcribe}
                            {--sync : Run the transcription synchronously instead of dispatching to the queue}';

    protected $description = 'Dispatch or run transcription for a sermon video';

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

        if ($this->option('sync')) {
            $this->info("Running transcription synchronously for sermon video #{$sermonVideo->id}...");
            TranscribeSermonVideoJob::dispatchSync($sermonVideo);

            $sermonVideo->refresh();

            if ($sermonVideo->transcript_status === TranscriptStatus::Completed) {
                $this->info('Transcription completed successfully.');
            } else {
                $this->error("Transcription failed: {$sermonVideo->transcript_error}");

                return self::FAILURE;
            }
        } else {
            TranscribeSermonVideoJob::dispatch($sermonVideo);
            $this->info("Transcription job dispatched for sermon video #{$sermonVideo->id}.");
        }

        return self::SUCCESS;
    }
}
