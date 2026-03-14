<?php

namespace App\Console\Commands;

use App\Models\VideoClip;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RenameClipVideos extends Command
{
    protected $signature = 'app:rename-clip-videos';

    protected $description = 'Rename existing clip video files to include the clip title';

    public function handle(): int
    {
        $clips = VideoClip::with('video')
            ->whereNotNull('clip_video_path')
            ->whereNotNull('title')
            ->get();

        if ($clips->isEmpty()) {
            $this->info('No clip videos to rename.');

            return self::SUCCESS;
        }

        $disk = Storage::disk('public');
        $renamed = 0;

        foreach ($clips as $clip) {
            $newPath = $clip->buildClipVideoPath();

            if ($newPath === $clip->clip_video_path) {
                continue;
            }

            if ($disk->exists($clip->clip_video_path)) {
                $disk->move($clip->clip_video_path, $newPath);
                $this->info("Renamed: {$clip->clip_video_path} -> {$newPath}");
            } else {
                $this->warn("File missing, updating path only: {$clip->clip_video_path} -> {$newPath}");
            }

            $clip->updateQuietly(['clip_video_path' => $newPath]);
            $renamed++;
        }

        $this->info("Renamed {$renamed} ".str('clip video')->plural($renamed).'.');

        return self::SUCCESS;
    }
}
