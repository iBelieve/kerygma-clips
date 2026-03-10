<?php

namespace App\Console\Commands;

use App\Jobs\ExtractVideoClipVerticalVideo;
use App\Models\Video;
use Illuminate\Console\Command;

class ExtractVideoClipVideos extends Command
{
    protected $signature = 'app:extract-video-clip-videos
                            {id : The ID of the video whose clips to regenerate}';

    protected $description = 'Regenerate all clip videos for a given video';

    public function handle(): int
    {
        $video = Video::find($this->argument('id'));

        if ($video === null) {
            $this->error("Video with ID {$this->argument('id')} not found.");

            return self::FAILURE;
        }

        $clips = $video->videoClips;

        if ($clips->isEmpty()) {
            $this->info("Video #{$video->id} has no clips.");

            return self::SUCCESS;
        }

        foreach ($clips as $clip) {
            ExtractVideoClipVerticalVideo::dispatch($clip);
            $this->info("Dispatched clip #{$clip->id} (segments {$clip->start_segment_index}-{$clip->end_segment_index}).");
        }

        $this->info("Dispatched {$clips->count()} clip extraction ".str('job')->plural($clips->count()).'.');

        return self::SUCCESS;
    }
}
