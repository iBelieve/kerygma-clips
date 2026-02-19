<?php

namespace App\Jobs;

use App\Enums\TranscriptStatus;
use App\Models\SermonVideo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class TranscribeSermonVideo implements ShouldQueue
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

    public function handle(): void
    {
        $this->sermonVideo->update([
            'transcript_status' => TranscriptStatus::Processing,
            'transcript_error' => null,
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
                    'uv', 'run',
                    'whisperx',
                    $absolutePath,
                    '--model', 'small',
                    '--output_format', 'json',
                    '--output_dir', $outputDir,
                    '--language', 'en',
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
                'transcript_status' => TranscriptStatus::Completed,
                'transcript' => $transcript,
            ]);
        } catch (\Throwable $e) {
            $this->sermonVideo->update([
                'transcript_status' => TranscriptStatus::Failed,
                'transcript_error' => $e->getMessage(),
            ]);
        } finally {
            $this->cleanupDirectory($outputDir);
        }
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
