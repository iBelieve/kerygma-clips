<?php

namespace App\Console\Commands;

use App\Jobs\ScanSermonVideos as ScanSermonVideosJob;
use Illuminate\Console\Command;

class ScanSermonVideos extends Command
{
    protected $signature = 'app:scan-sermon-videos
                            {--transcribe : Dispatch transcription jobs for newly created sermon videos}
                            {--convert-to-vertical : Dispatch vertical video conversion jobs for newly created sermon videos}
                            {--queue : Dispatch the scan to the queue instead of running synchronously}';

    protected $description = 'Scan the sermon_videos disk for new video files and create SermonVideo entries';

    public function handle(): int
    {
        $transcribe = $this->option('transcribe');
        $convertToVertical = $this->option('convert-to-vertical');

        if ($this->option('queue')) {
            ScanSermonVideosJob::dispatch(
                verbose: true,
                transcribe: $transcribe,
                convertToVertical: $convertToVertical,
            );
            $this->info('Scan job dispatched to queue.');
        } else {
            $this->info('Running scan...');
            ScanSermonVideosJob::dispatchSync(
                verbose: true,
                transcribe: $transcribe,
                convertToVertical: $convertToVertical,
            );
            $this->info('Done.');
        }

        return self::SUCCESS;
    }
}
