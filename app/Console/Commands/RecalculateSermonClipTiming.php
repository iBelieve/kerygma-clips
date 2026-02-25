<?php

namespace App\Console\Commands;

use App\Jobs\ExtractSermonClipVerticalVideo;
use App\Models\SermonClip;
use Illuminate\Console\Command;

class RecalculateSermonClipTiming extends Command
{
    protected $signature = 'app:recalculate-sermon-clip-timing';

    protected $description = 'Recalculate timing for all sermon clips and queue clip video extraction';

    public function handle(): int
    {
        $clips = SermonClip::with('sermonVideo')->get();

        if ($clips->isEmpty()) {
            $this->info('No sermon clips found.');

            return self::SUCCESS;
        }

        foreach ($clips as $clip) {
            $clip->save();
            ExtractSermonClipVerticalVideo::dispatch($clip);
            $this->info("Recalculated and dispatched clip #{$clip->id} (segments {$clip->start_segment_index}-{$clip->end_segment_index}).");
        }

        $this->info("Recalculated {$clips->count()} ".str('clip')->plural($clips->count()).'.');

        return self::SUCCESS;
    }
}
