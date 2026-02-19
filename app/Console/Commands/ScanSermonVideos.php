<?php

namespace App\Console\Commands;

use App\Models\SermonVideo;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ScanSermonVideos extends Command
{
    protected $signature = 'app:scan-sermon-videos';

    protected $description = 'Scan the sermon_videos disk for new video files and create SermonVideo entries';

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

    public function handle(): int
    {
        $disk = Storage::disk('sermon_videos');
        $files = $disk->files();

        $videoFiles = array_filter($files, function (string $file): bool {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            return in_array($extension, self::VIDEO_EXTENSIONS, true);
        });

        if (empty($videoFiles)) {
            $this->info('No video files found on the sermon_videos disk.');

            return self::SUCCESS;
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
                $skipped++;

                continue;
            }

            $date = $this->parseDateFromFilename($file);
            if ($date === null) {
                $this->warn("Unable to parse date from filename: {$file}");
                $skipped++;

                continue;
            }

            SermonVideo::create([
                'raw_video_path' => $file,
                'date' => $date,
            ]);

            $this->info("Created sermon video for {$file} with date {$date->toDateTimeString()}");
            $created++;
        }

        $this->info("Scan complete: {$created} created, {$skipped} skipped.");

        return self::SUCCESS;
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
