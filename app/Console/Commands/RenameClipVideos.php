<?php

namespace App\Console\Commands;

use App\Models\VideoClip;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RenameClipVideos extends Command
{
    protected $signature = 'app:rename-clip-videos';

    protected $description = 'Rename clip video files to match the current filename format';

    public function handle(): int
    {
        $clips = VideoClip::with('video')
            ->whereNotNull('clip_video_path')
            ->get();

        if ($clips->isEmpty()) {
            $this->info('No clip videos found.');

            return self::SUCCESS;
        }

        $disk = Storage::disk('public');
        $renamed = 0;
        $skipped = 0;

        foreach ($clips as $clip) {
            $expectedPath = $clip->buildClipVideoPath();

            if ($expectedPath === $clip->clip_video_path) {
                $skipped++;

                continue;
            }

            if ($disk->exists($clip->clip_video_path)) {
                $disk->move($clip->clip_video_path, $expectedPath);
                $this->info("Renamed: {$clip->clip_video_path} -> {$expectedPath}");
            } else {
                $this->warn("File missing, updating path only: {$clip->clip_video_path} -> {$expectedPath}");
            }

            $clip->updateQuietly(['clip_video_path' => $expectedPath]);
            $renamed++;
        }

        $this->info("Done. Renamed {$renamed}, skipped {$skipped} already correct.");

        return self::SUCCESS;
    }
}
