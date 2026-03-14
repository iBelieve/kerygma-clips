<?php

namespace App\Jobs;

use App\Enums\JobStatus;
use App\Enums\VideoType;
use App\Models\Video;
use App\Services\VideoProbe;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ScanImportVideos implements ShouldBeUnique, ShouldQueue
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

    public function __construct(
        public bool $verbose = false,
        public bool $transcribe = true,
        public bool $convertToVertical = true,
        public bool $includeRecent = false,
    ) {}

    public function handle(VideoProbe $videoProbe): void
    {
        $disk = Storage::disk('import_videos');
        $files = $disk->files();

        $videoFiles = array_filter($files, function (string $file): bool {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            return in_array($extension, self::VIDEO_EXTENSIONS, true);
        });

        if (empty($videoFiles)) {
            if ($this->verbose) {
                Log::warning('No video files found on the import_videos disk.');
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

            $date = CarbonImmutable::createFromTimestamp($disk->lastModified($file));
            $title = pathinfo($file, PATHINFO_FILENAME);

            $absolutePath = $disk->path($file);
            $duration = $videoProbe->getDurationInSeconds($absolutePath);

            Video::create([
                'type' => VideoType::Import,
                'raw_video_path' => $file,
                'title' => $title,
                'date' => $date->utc(),
                'duration' => $duration,
                'vertical_video_crop_center' => 50,
            ]);

            Log::info("Created import video for {$file}", [
                'title' => $title,
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

        if ($this->verbose) {
            Log::info("Import scan complete: {$created} created, {$skipped} skipped.");
        }
    }
}
