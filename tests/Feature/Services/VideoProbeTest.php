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

test('it returns video dimensions from ffprobe json output', function () {
    Process::fake([
        '*' => Process::result(output: json_encode([
            'streams' => [['width' => 1920, 'height' => 1080]],
        ])),
    ]);

    $probe = new VideoProbe;
    $dimensions = $probe->getVideoDimensions('/path/to/video.mp4');

    expect($dimensions)->toBe(['width' => 1920, 'height' => 1080]);
});

test('it returns dimensions for vertical video', function () {
    Process::fake([
        '*' => Process::result(output: json_encode([
            'streams' => [['width' => 1080, 'height' => 1920]],
        ])),
    ]);

    $probe = new VideoProbe;
    $dimensions = $probe->getVideoDimensions('/path/to/video.mov');

    expect($dimensions)->toBe(['width' => 1080, 'height' => 1920]);
});

test('it returns null when ffprobe returns no dimensions', function () {
    Process::fake([
        '*' => Process::result(exitCode: 1, errorOutput: 'No such file'),
    ]);

    $probe = new VideoProbe;
    $dimensions = $probe->getVideoDimensions('/path/to/video.mp4');

    expect($dimensions)->toBeNull();
});

test('it returns null when ffprobe returns no streams', function () {
    Process::fake([
        '*' => Process::result(output: json_encode(['streams' => []])),
    ]);

    $probe = new VideoProbe;
    $dimensions = $probe->getVideoDimensions('/path/to/video.mp4');

    expect($dimensions)->toBeNull();
});

test('it uses only first stream when ffprobe returns multiple streams', function () {
    Process::fake([
        '*' => Process::result(output: json_encode([
            'streams' => [
                ['width' => 1080, 'height' => 1920],
                ['width' => 320, 'height' => 240],
            ],
        ])),
    ]);

    $probe = new VideoProbe;
    $dimensions = $probe->getVideoDimensions('/path/to/video.mov');

    expect($dimensions)->toBe(['width' => 1080, 'height' => 1920]);
});

test('it swaps dimensions for video with -90 degree rotation in side_data', function () {
    Process::fake([
        '*' => Process::result(output: json_encode([
            'streams' => [[
                'width' => 1920,
                'height' => 1080,
                'side_data_list' => [
                    ['side_data_type' => 'Display Matrix', 'rotation' => -90],
                ],
            ]],
        ])),
    ]);

    $probe = new VideoProbe;
    $dimensions = $probe->getVideoDimensions('/path/to/video.mov');

    expect($dimensions)->toBe(['width' => 1080, 'height' => 1920]);
});

test('it swaps dimensions for video with 90 degree rotation in side_data', function () {
    Process::fake([
        '*' => Process::result(output: json_encode([
            'streams' => [[
                'width' => 1920,
                'height' => 1080,
                'side_data_list' => [
                    ['side_data_type' => 'Display Matrix', 'rotation' => 90],
                ],
            ]],
        ])),
    ]);

    $probe = new VideoProbe;
    $dimensions = $probe->getVideoDimensions('/path/to/video.mov');

    expect($dimensions)->toBe(['width' => 1080, 'height' => 1920]);
});

test('it swaps dimensions for video with rotate tag in stream tags', function () {
    Process::fake([
        '*' => Process::result(output: json_encode([
            'streams' => [[
                'width' => 1920,
                'height' => 1080,
                'tags' => ['rotate' => '90'],
            ]],
        ])),
    ]);

    $probe = new VideoProbe;
    $dimensions = $probe->getVideoDimensions('/path/to/video.mp4');

    expect($dimensions)->toBe(['width' => 1080, 'height' => 1920]);
});

test('it does not swap dimensions for 180 degree rotation', function () {
    Process::fake([
        '*' => Process::result(output: json_encode([
            'streams' => [[
                'width' => 1920,
                'height' => 1080,
                'side_data_list' => [
                    ['side_data_type' => 'Display Matrix', 'rotation' => 180],
                ],
            ]],
        ])),
    ]);

    $probe = new VideoProbe;
    $dimensions = $probe->getVideoDimensions('/path/to/video.mov');

    expect($dimensions)->toBe(['width' => 1920, 'height' => 1080]);
});

test('it does not swap dimensions when no rotation metadata present', function () {
    Process::fake([
        '*' => Process::result(output: json_encode([
            'streams' => [['width' => 1920, 'height' => 1080]],
        ])),
    ]);

    $probe = new VideoProbe;
    $dimensions = $probe->getVideoDimensions('/path/to/video.mp4');

    expect($dimensions)->toBe(['width' => 1920, 'height' => 1080]);
});

test('it rounds duration to nearest second', function () {
    Process::fake([
        '*' => Process::result(output: '45.7'),
    ]);

    $probe = new VideoProbe;
    $duration = $probe->getDurationInSeconds('/path/to/video.mp4');

    expect($duration)->toBe(46);
});
