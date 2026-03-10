<?php

use App\Services\VideoProbe;
use Illuminate\Support\Facades\Process;

test('it returns duration in seconds from ffprobe output', function () {
    Process::fake([
        '*' => Process::result(output: '3723.456'),
    ]);

    $probe = new VideoProbe;
    $duration = $probe->getDurationInSeconds('/path/to/video.mp4');

    expect($duration)->toBe(3723);

    Process::assertRan(function ($process) {
        return str_contains(implode(' ', $process->command), 'ffprobe');
    });
});

test('it returns null when ffprobe fails', function () {
    Process::fake([
        '*' => Process::result(exitCode: 1, errorOutput: 'No such file'),
    ]);

    $probe = new VideoProbe;
    $duration = $probe->getDurationInSeconds('/path/to/nonexistent.mp4');

    expect($duration)->toBeNull();
});

test('it returns null when ffprobe returns non-numeric output', function () {
    Process::fake([
        '*' => Process::result(output: 'N/A'),
    ]);

    $probe = new VideoProbe;
    $duration = $probe->getDurationInSeconds('/path/to/corrupt.mp4');

    expect($duration)->toBeNull();
});

test('it returns video dimensions from ffprobe output', function () {
    Process::fake([
        '*' => Process::result(output: '1920x1080'),
    ]);

    $probe = new VideoProbe;
    $dimensions = $probe->getVideoDimensions('/path/to/video.mp4');

    expect($dimensions)->toBe(['width' => 1920, 'height' => 1080]);
});

test('it returns null when ffprobe returns no dimensions', function () {
    Process::fake([
        '*' => Process::result(exitCode: 1, errorOutput: 'No such file'),
    ]);

    $probe = new VideoProbe;
    $dimensions = $probe->getVideoDimensions('/path/to/video.mp4');

    expect($dimensions)->toBeNull();
});

test('it parses dimensions from first line when ffprobe returns multi-line output', function () {
    // iPhone .mov files can have multiple video streams, producing extra ffprobe output
    Process::fake([
        '*' => Process::result(output: "1080x1920\n1080x1920"),
    ]);

    $probe = new VideoProbe;
    $dimensions = $probe->getVideoDimensions('/path/to/video.mov');

    expect($dimensions)->toBe(['width' => 1080, 'height' => 1920]);
});

test('it handles ffprobe output with trailing separator from extra streams', function () {
    // Some .mov files produce output like "1080x1920\n" with partial extra lines
    Process::fake([
        '*' => Process::result(output: "1080x1920\nx"),
    ]);

    $probe = new VideoProbe;
    $dimensions = $probe->getVideoDimensions('/path/to/video.mov');

    expect($dimensions)->toBe(['width' => 1080, 'height' => 1920]);
});

test('it rounds duration to nearest second', function () {
    Process::fake([
        '*' => Process::result(output: '45.7'),
    ]);

    $probe = new VideoProbe;
    $duration = $probe->getDurationInSeconds('/path/to/video.mp4');

    expect($duration)->toBe(46);
});
