<?php

namespace App\Jobs;

use App\Models\SermonVideo;
use App\Services\VideoProbe;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ScanSermonVideos implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    private const VIDEO_EXTENSIONS = [
        'mp4',
        'mov',
        'avi',
        'mkv',
        'webm',
        'wmv',
        'm4v',
        'flv',
    ];

    private const MIN_AGE_MINUTES = 5;

    private const FILENAME_DATE_PATTERN = '/^(\d{4}-\d{2}-\d{2}) (\d{2}-\d{2}-\d{2})$/';

    private const TIMEZONE = 'America/Chicago';

    public function __construct(
        public bool $verbose = false,
    ) {}

    public function handle(VideoProbe $videoProbe): void
    {
        $disk = Storage::disk('sermon_videos');
        $files = $disk->files();

        $videoFiles = array_filter($files, function (string $file): bool {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            return in_array($extension, self::VIDEO_EXTENSIONS, true);
        });

        if (empty($videoFiles)) {
            if ($this->verbose) {
                Log::warning('No video files found on the sermon_videos disk.');
            }

            return;
        }

        $existingPaths = SermonVideo::whereIn('raw_video_path', $videoFiles)
            ->pluck('raw_video_path')
            ->flip()
            ->all();

        $now = Carbon::now();
        $created = 0;
        $skipped = 0;

        foreach ($videoFiles as $file) {
            if (isset($existingPaths[$file])) {
                $skipped++;

                continue;
            }

            $lastModified = Carbon::createFromTimestamp($disk->lastModified($file));
            if ($lastModified->gt($now->copy()->subMinutes(self::MIN_AGE_MINUTES))) {
                if ($this->verbose) {
                    Log::info("Skipping recently modified file: {$file}");
                }
                $skipped++;

                continue;
            }

            $date = $this->parseDateFromFilename($file);
            if ($date === null) {
                if ($this->verbose) {
                    Log::warning("Unable to parse date from filename: {$file}");
                }
                $skipped++;

                continue;
            }

            $absolutePath = $disk->path($file);
            $duration = $videoProbe->getDurationInSeconds($absolutePath);

            $sermonVideo = SermonVideo::create([
                'raw_video_path' => $file,
                'date' => $date->utc(),
                'duration' => $duration,
            ]);

            TranscribeSermonVideo::dispatch($sermonVideo);

            Log::info("Created sermon video for {$file}", [
                'date' => $date->toDateTimeString(),
                'duration' => $duration,
            ]);

            $created++;
        }

        if ($this->verbose) {
            Log::info("Scan complete: {$created} created, {$skipped} skipped.");
        }
    }

    private function parseDateFromFilename(string $file): ?Carbon
    {
        $name = pathinfo($file, PATHINFO_FILENAME);

        if (! preg_match(self::FILENAME_DATE_PATTERN, $name, $matches)) {
            return null;
        }

        $dateStr = $matches[1];
        $timeStr = str_replace('-', ':', $matches[2]);

        return Carbon::parse("{$dateStr} {$timeStr}", self::TIMEZONE);
    }
}
