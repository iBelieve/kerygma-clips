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
            '-show_entries', 'stream=width,height:stream_side_data=rotation:stream_tags=rotate',
            '-of', 'json',
            $absolutePath,
        ]);

        if (! $result->successful()) {
            Log::warning("ffprobe failed for {$absolutePath}: {$result->errorOutput()}");

            return null;
        }

        $data = json_decode(trim($result->output()), true);
        $stream = $data['streams'][0] ?? null;

        if (! $stream || ! isset($stream['width'], $stream['height'])) {
            Log::warning("ffprobe returned no video dimensions for {$absolutePath}: {$result->output()}");

            return null;
        }

        $width = (int) $stream['width'];
        $height = (int) $stream['height'];

        // Detect rotation from side_data (displaymatrix) or stream tags.
        // iPhone videos are often stored landscape with a rotation flag.
        // ffmpeg auto-rotates during processing, so we return the effective
        // (post-rotation) dimensions to match what filters will operate on.
        $rotation = $this->detectRotation($stream);

        if ($rotation === 90 || $rotation === 270) {
            [$width, $height] = [$height, $width];
        }

        return [
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * Detect video rotation from stream side data or tags.
     *
     * Returns the absolute rotation in degrees (0, 90, 180, 270) or 0 if none detected.
     */
    private function detectRotation(array $stream): int
    {
        // Check side_data (displaymatrix rotation, used by newer containers)
        foreach ($stream['side_data_list'] ?? [] as $sideData) {
            if (isset($sideData['rotation'])) {
                return abs((int) $sideData['rotation']) % 360;
            }
        }

        // Check stream tags (older metadata format, e.g. 'rotate' tag)
        if (isset($stream['tags']['rotate'])) {
            return abs((int) $stream['tags']['rotate']) % 360;
        }

        return 0;
    }
}
