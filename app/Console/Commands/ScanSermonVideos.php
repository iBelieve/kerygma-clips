<?php

namespace App\Console\Commands;

use App\Jobs\ScanSermonVideos as ScanSermonVideosJob;
use Illuminate\Console\Command;

class ScanSermonVideos extends Command
{
    protected $signature = 'app:scan-sermon-videos {--sync : Run synchronously instead of dispatching to queue}';

    protected $description = 'Scan the sermon_videos disk for new video files and create SermonVideo entries';

    public function handle(): int
    {
        if ($this->option('sync')) {
            $this->info('Running scan synchronously...');
            ScanSermonVideosJob::dispatchSync(verbose: true);
        } else {
            $this->info('Dispatching scan job to queue...');
            ScanSermonVideosJob::dispatch(verbose: true);
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
