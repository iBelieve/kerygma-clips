<?php

namespace App\Console\Commands;

use App\Enums\JobStatus;
use App\Jobs\ExtractVideoClipVerticalVideo;
use App\Models\VideoClip;
use Illuminate\Console\Command;

class RecalculateVideoClipTiming extends Command
{
    protected $signature = 'app:recalculate-video-clip-timing';

    protected $description = 'Recalculate timing for all video clips and queue clip video extraction';

    public function handle(): int
    {
        $clips = VideoClip::with('video')->get();

        if ($clips->isEmpty()) {
            $this->info('No video clips found.');

            return self::SUCCESS;
        }

        foreach ($clips as $clip) {
            $clip->clip_video_status = JobStatus::Pending;
            $clip->save();
            ExtractVideoClipVerticalVideo::dispatch($clip);
            $this->info("Recalculated and dispatched clip #{$clip->id} (segments {$clip->start_segment_index}-{$clip->end_segment_index}).");
        }

        $this->info("Recalculated {$clips->count()} ".str('clip')->plural($clips->count()).'.');

        return self::SUCCESS;
    }
}
