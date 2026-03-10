<?php

namespace App\Jobs;

use App\Enums\JobStatus;
use App\Enums\VideoType;
use App\Models\Video;
use App\Services\VideoProbe;
use App\Support\DateTimeHelpers;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ScanSermonVideos implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        public bool $transcribe = true,
        public bool $convertToVertical = true,
        public bool $includeRecent = false,
    ) {}

    public function handle(VideoProbe $videoProbe): void
    {
        $disk = Storage::disk('sermon_videos');
        $files = $disk->files();

        $latestCropCenter = Video::query()
            ->whereNotNull('vertical_video_crop_center')
            ->latest('date')
            ->value('vertical_video_crop_center');

        $videoFiles = array_filter($files, function (string $file): bool {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            return in_array($extension, self::VIDEO_EXTENSIONS, true);
        });

        if (empty($videoFiles)) {
            if ($this->verbose) {
                Log::warning('No video files found on the sermon_videos disk.');
            }
        }

        $existingPaths = Video::whereIn('raw_video_path', $videoFiles)
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

            if (! $this->includeRecent) {
                $lastModified = Carbon::createFromTimestamp($disk->lastModified($file));
                if ($lastModified->gt($now->copy()->subMinutes(self::MIN_AGE_MINUTES))) {
                    if ($this->verbose) {
                        Log::info("Skipping recently modified file: {$file}");
                    }
                    $skipped++;

                    continue;
                }
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

            Video::create([
                'type' => VideoType::Sermon,
                'raw_video_path' => $file,
                'date' => $date->utc(),
                'duration' => $duration,
                'vertical_video_crop_center' => $latestCropCenter ?? 50,
            ]);

            Log::info("Created sermon video for {$file}", [
                'date' => $date->toDateTimeString(),
                'duration' => $duration,
            ]);

            $created++;
        }

        if ($this->transcribe) {
            $pending = Video::where('transcript_status', JobStatus::Pending)->get();

            foreach ($pending as $video) {
                TranscribeVideo::dispatch($video);
            }

            if ($this->verbose && $pending->isNotEmpty()) {
                Log::info("Dispatched transcription for {$pending->count()} pending videos.");
            }
        }

        if ($this->convertToVertical) {
            $pendingVertical = Video::where('vertical_video_status', JobStatus::Pending)->get();

            foreach ($pendingVertical as $video) {
                ConvertToVerticalVideo::dispatch($video);
            }

            if ($this->verbose && $pendingVertical->isNotEmpty()) {
                Log::info("Dispatched vertical video conversion for {$pendingVertical->count()} pending videos.");
            }
        }

        $missingFrames = Video::whereNull('preview_frame_path')->get();

        foreach ($missingFrames as $video) {
            ExtractPreviewFrame::dispatch($video);
        }

        if ($this->verbose && $missingFrames->isNotEmpty()) {
            Log::info("Dispatched preview frame extraction for {$missingFrames->count()} videos.");
        }

        $this->hydrateSermonMetadata();

        if ($this->verbose) {
            Log::info("Scan complete: {$created} created, {$skipped} skipped.");
        }
    }

    private function hydrateSermonMetadata(): void
    {
        $metadataFields = ['title', 'subtitle', 'scripture', 'preacher', 'color'];

        try {
            $response = Http::get('https://mluther.org/api/sermons');

            if ($response->failed()) {
                Log::warning('Failed to fetch sermons API: HTTP '.$response->status());

                return;
            }

            /** @var array<int, array{date: string, title: string, subtitle: string, scripture: string, color: string, preacher: string}> $sermonsData */
            $sermonsData = $response->json('data', []);
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch sermons API: '.$e->getMessage());

            return;
        }

        $sermons = collect($sermonsData)->map(fn (array $sermon) => [
            ...$sermon,
            'parsed_date' => Carbon::parse($sermon['date']),
        ]);

        $recentVideos = Video::where('type', VideoType::Sermon)->latest('date')->take(10)->get();
        foreach ($recentVideos as $video) {
            $nullFields = array_filter($metadataFields, fn (string $field) => $video->{$field} === null);

            if (empty($nullFields)) {
                continue;
            }

            $videoDate = $video->date->setTimezone(self::TIMEZONE)->format('Y-m-d');

            $sameDaySermons = $sermons->filter(
                fn (array $sermon) => $sermon['parsed_date']->setTimezone(self::TIMEZONE)->format('Y-m-d') === $videoDate
            );

            if ($sameDaySermons->isEmpty()) {
                continue;
            }

            $videoTimestamp = $video->date->getTimestamp();

            $closestSermon = $sameDaySermons->reduce(function (?array $prev, array $curr) use ($videoTimestamp) {
                if ($prev === null) {
                    return $curr;
                }

                return abs($curr['parsed_date']->getTimestamp() - $videoTimestamp)
                    < abs($prev['parsed_date']->getTimestamp() - $videoTimestamp)
                    ? $curr
                    : $prev;
            });

            foreach ($nullFields as $field) {
                if (isset($closestSermon[$field])) {
                    $video->{$field} = $closestSermon[$field];
                }
            }

            $video->save();
        }

        if ($this->verbose) {
            Log::info('Sermon metadata hydration complete.');
        }
    }

    private function parseDateFromFilename(string $file): ?CarbonImmutable
    {
        $name = pathinfo($file, PATHINFO_FILENAME);

        if (! preg_match(self::FILENAME_DATE_PATTERN, $name, $matches)) {
            return null;
        }

        $dateStr = $matches[1];
        $timeStr = str_replace('-', ':', $matches[2]);

        return DateTimeHelpers::roundToNearestHalfHour(
            CarbonImmutable::parse("{$dateStr} {$timeStr}", self::TIMEZONE)
        );
    }
}
