<?php

namespace App\Jobs;

use App\Enums\JobStatus;
use App\Models\SermonVideo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class TranscribeSermonVideo implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 3600;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    public function __construct(
        public SermonVideo $sermonVideo
    ) {
        $this->onQueue('transcription');
    }

    public function uniqueId(): int
    {
        return $this->sermonVideo->id;
    }

    public function handle(): void
    {
        $this->sermonVideo->update([
            'transcript_status' => JobStatus::Processing,
            'transcript_error' => null,
            'transcription_started_at' => now(),
            'transcription_completed_at' => null,
        ]);

        $disk = Storage::disk('sermon_videos');
        $absolutePath = $disk->path($this->sermonVideo->raw_video_path);

        $outputDir = sys_get_temp_dir().'/whisperx_'.$this->sermonVideo->id;
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        try {
            $result = Process::path(base_path())
                ->timeout(3600)
                ->run([
                    'whisperx',
                    $absolutePath,
                    '--model', 'large-v3',
                    '--output_format', 'json',
                    '--output_dir', $outputDir,
                    '--language', 'en',
                    // Use int8 quantization for CPU-only inference. This significantly
                    // reduces memory usage and speeds up transcription compared to
                    // float32/float16, which require a GPU to run efficiently.
                    '--compute_type', 'int8',
                ]);

            if ($result->failed()) {
                throw new \RuntimeException($result->errorOutput() ?: $result->output());
            }

            $inputFilename = pathinfo($this->sermonVideo->raw_video_path, PATHINFO_FILENAME);
            $jsonPath = $outputDir.'/'.$inputFilename.'.json';

            if (! file_exists($jsonPath)) {
                throw new \RuntimeException(
                    "WhisperX did not produce expected output file: {$jsonPath}"
                );
            }

            $transcriptJson = file_get_contents($jsonPath);
            $transcript = json_decode($transcriptJson, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException(
                    'Failed to parse WhisperX JSON output: '.json_last_error_msg()
                );
            }

            $this->sermonVideo->update([
                'transcript_status' => JobStatus::Completed,
                'transcript' => $transcript,
                'transcription_completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $isTimeout = $e instanceof ProcessTimedOutException;

            Log::error('Transcription failed', [
                'video_path' => $this->sermonVideo->raw_video_path,
                'exception' => $e,
            ]);

            $this->sermonVideo->update([
                'transcript_status' => $isTimeout ? JobStatus::TimedOut : JobStatus::Failed,
                'transcript_error' => $e->getMessage(),
            ]);
        } finally {
            $this->cleanupDirectory($outputDir);
        }
    }

    public function failed(\Throwable $exception): void
    {
        if ($this->sermonVideo->transcript_status !== JobStatus::Processing) {
            return;
        }

        $isTimeout = $exception instanceof TimeoutExceededException
            || $exception instanceof ProcessTimedOutException
            || $exception instanceof \Symfony\Component\Process\Exception\ProcessTimedOutException;

        $this->sermonVideo->update([
            'transcript_status' => $isTimeout ? JobStatus::TimedOut : JobStatus::Failed,
            'transcript_error' => $exception->getMessage(),
        ]);
    }

    private function cleanupDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = glob($dir.'/*');
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        rmdir($dir);
    }
}
