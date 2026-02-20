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
}
