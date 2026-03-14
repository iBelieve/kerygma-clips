<?php

namespace App\Console\Commands;

use App\Models\Video;
use App\Services\VideoProbe;
use Illuminate\Console\Command;

class RefreshVideoMetadata extends Command
{
    protected $signature = 'app:refresh-video-metadata
                            {id? : The ID of a specific video to refresh}';

    protected $description = 'Refresh source video metadata (dimensions, aspect ratio, orientation)';

    public function handle(VideoProbe $videoProbe): int
    {
        $id = $this->argument('id');

        if ($id !== null) {
            $video = Video::find($id);

            if ($video === null) {
                $this->error("Video with ID {$id} not found.");

                return self::FAILURE;
            }

            return $this->refreshVideo($video, $videoProbe) ? self::SUCCESS : self::FAILURE;
        }

        $videos = Video::all();

        if ($videos->isEmpty()) {
            $this->info('No videos found.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($videos->count());
        $bar->start();

        $failed = 0;

        foreach ($videos as $video) {
            if (! $this->refreshVideo($video, $videoProbe)) {
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        if ($failed > 0) {
            $this->warn("{$failed} video(s) could not be probed.");
        }

        $this->info('Done.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function refreshVideo(Video $video, VideoProbe $videoProbe): bool
    {
        $disk = $video->rawVideoDisk();

        if (! $disk->exists($video->raw_video_path)) {
            $this->warn("Video #{$video->id}: raw file not found at {$video->raw_video_path}");

            return false;
        }

        $absolutePath = $disk->path($video->raw_video_path);
        $metadata = $videoProbe->getVideoMetadata($absolutePath);

        if ($metadata === null) {
            $this->warn("Video #{$video->id}: ffprobe failed for {$video->raw_video_path}");

            return false;
        }

        $video->update($metadata);

        return true;
    }
}
