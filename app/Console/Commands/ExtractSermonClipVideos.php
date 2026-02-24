<?php

namespace App\Console\Commands;

use App\Jobs\ExtractSermonClipVerticalVideo;
use App\Models\SermonVideo;
use Illuminate\Console\Command;

class ExtractSermonClipVideos extends Command
{
    protected $signature = 'app:extract-sermon-clip-videos
                            {id : The ID of the sermon video whose clips to regenerate}';

    protected $description = 'Regenerate all sermon clip videos for a given sermon video';

    public function handle(): int
    {
        $sermonVideo = SermonVideo::find($this->argument('id'));

        if ($sermonVideo === null) {
            $this->error("Sermon video with ID {$this->argument('id')} not found.");

            return self::FAILURE;
        }

        $clips = $sermonVideo->sermonClips;

        if ($clips->isEmpty()) {
            $this->info("Sermon video #{$sermonVideo->id} has no clips.");

            return self::SUCCESS;
        }

        foreach ($clips as $clip) {
            ExtractSermonClipVerticalVideo::dispatch($clip);
            $this->info("Dispatched clip #{$clip->id} (segments {$clip->start_segment_index}-{$clip->end_segment_index}).");
        }

        $this->info("Dispatched {$clips->count()} clip extraction ".str('job')->plural($clips->count()).'.');

        return self::SUCCESS;
    }
}
