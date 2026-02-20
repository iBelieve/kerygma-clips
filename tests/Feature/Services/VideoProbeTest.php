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

test('it rounds duration to nearest second', function () {
    Process::fake([
        '*' => Process::result(output: '45.7'),
    ]);

    $probe = new VideoProbe;
    $duration = $probe->getDurationInSeconds('/path/to/video.mp4');

    expect($duration)->toBe(46);
});
