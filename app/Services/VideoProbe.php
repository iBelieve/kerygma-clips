<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class VideoProbe
{
    public function getDurationInSeconds(string $absolutePath): ?int
    {
        $result = Process::run([
            'ffprobe',
            '-v', 'quiet',
            '-show_entries', 'format=duration',
            '-of', 'csv=p=0',
            $absolutePath,
        ]);

        if (! $result->successful()) {
            Log::warning("ffprobe failed for {$absolutePath}: {$result->errorOutput()}");

            return null;
        }

        $output = trim($result->output());

        if (! is_numeric($output)) {
            Log::warning("ffprobe returned non-numeric duration for {$absolutePath}: {$output}");

            return null;
        }

        return (int) round((float) $output);
    }

    /**
     * @return array{width: int, height: int}|null
     */
    public function getVideoDimensions(string $absolutePath): ?array
    {
        $result = Process::run([
            'ffprobe',
            '-v', 'quiet',
            '-select_streams', 'v:0',
            '-show_entries', 'stream=width,height',
            '-of', 'csv=s=x:p=0',
            $absolutePath,
        ]);

        if (! $result->successful()) {
            Log::warning("ffprobe failed for {$absolutePath}: {$result->errorOutput()}");

            return null;
        }

        $output = trim($result->output());

        if (! preg_match('/^(\d+)x(\d+)$/', $output, $matches)) {
            Log::warning("ffprobe returned unexpected dimensions for {$absolutePath}: {$output}");

            return null;
        }

        return [
            'width' => (int) $matches[1],
            'height' => (int) $matches[2],
        ];
    }
}
