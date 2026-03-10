<?php

namespace App\Jobs;

use App\Models\Video;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class ExtractPreviewFrame implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public Video $video
    ) {}

    public function uniqueId(): int
    {
        return $this->video->id;
    }

    public function handle(): void
    {
        $inputDisk = Storage::disk('sermon_videos');
        $outputDisk = Storage::disk('public');
        $absolutePath = $inputDisk->path($this->video->raw_video_path);

        $seekTime = max(0, (int) floor(($this->video->duration ?? 0) / 2));

        $inputFilename = pathinfo($this->video->raw_video_path, PATHINFO_FILENAME);
        $outputRelativePath = "frames/{$inputFilename}.jpg";
        $outputAbsolutePath = $outputDisk->path($outputRelativePath);

        $outputDir = dirname($outputAbsolutePath);
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        try {
            $result = Process::timeout(60)->run([
                'ffmpeg',
                '-ss', (string) $seekTime,
                '-i', $absolutePath,
                '-frames:v', '1',
                '-q:v', '2',
                '-y', $outputAbsolutePath,
            ]);

            if ($result->failed()) {
                throw new \RuntimeException($result->errorOutput() ?: $result->output());
            }

            $this->video->update([
                'preview_frame_path' => $outputRelativePath,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Preview frame extraction failed', [
                'video_path' => $this->video->raw_video_path,
                'exception' => $e,
            ]);
        }
    }
}
