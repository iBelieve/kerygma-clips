<?php

namespace App\Console\Commands;

use App\Enums\JobStatus;
use App\Jobs\TranscribeVideo as TranscribeVideoJob;
use App\Models\Video;
use Illuminate\Console\Command;

class TranscribeVideo extends Command
{
    protected $signature = 'app:transcribe-video
                            {id : The ID of the video to transcribe}';

    protected $description = 'Run transcription for a video';

    public function handle(): int
    {
        $video = Video::find($this->argument('id'));

        if ($video === null) {
            $this->error("Video with ID {$this->argument('id')} not found.");

            return self::FAILURE;
        }

        if ($video->transcript_status === JobStatus::Processing) {
            $this->error("Video #{$video->id} is already being processed.");

            return self::FAILURE;
        }

        $this->info("Running transcription for video #{$video->id}...");
        TranscribeVideoJob::dispatchSync($video);

        $video->refresh();

        if ($video->transcript_status === JobStatus::Completed) {
            $this->info('Transcription completed successfully.');
        } else {
            $this->error("Transcription failed: {$video->transcript_error}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
