<?php

use App\Models\Settings;
use App\Models\Video;
use App\Models\VideoClip;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function createClipWithVideo(array $videoAttributes = []): VideoClip
{
    $segments = [];
    for ($i = 0; $i < 6; $i++) {
        $segments[] = [
            'start' => $i * 5.0,
            'end' => ($i + 1) * 5.0,
            'text' => "Segment {$i}",
        ];
    }

    $video = Video::factory()->create(array_merge([
        'transcript' => ['segments' => $segments],
        'duration' => 30,
    ], $videoAttributes));

    return VideoClip::factory()->create([
        'video_id' => $video->id,
    ]);
}

test('buildDescription includes excerpt and all sermon video fields', function () {
    $clip = createClipWithVideo([
        'scripture' => 'John 3:16',
        'subtitle' => 'The Good News',
        'preacher' => 'Pastor Smith',
    ]);

    $result = $clip->buildDescription('This is the excerpt.');

    expect($result)->toContain('This is the excerpt.')
        ->toContain('Clip from a sermon on John 3:16 for the Good News by Pastor Smith.');
});

test('buildDescription omits sermon line when no fields are present', function () {
    $clip = createClipWithVideo([
        'scripture' => null,
        'subtitle' => null,
        'preacher' => null,
    ]);

    $result = $clip->buildDescription('Just an excerpt.');

    expect($result)->not->toContain('Clip from a sermon');
});

test('buildDescription includes only scripture when other fields are null', function () {
    $clip = createClipWithVideo([
        'scripture' => 'Romans 8:28',
        'subtitle' => null,
        'preacher' => null,
    ]);

    $result = $clip->buildDescription('Excerpt text.');

    expect($result)->toContain('Clip from a sermon on Romans 8:28.');
});

test('buildDescription includes only preacher when other fields are null', function () {
    $clip = createClipWithVideo([
        'scripture' => null,
        'subtitle' => null,
        'preacher' => 'Pastor Jones',
    ]);

    $result = $clip->buildDescription('Excerpt text.');

    expect($result)->toContain('Clip from a sermon by Pastor Jones.');
});

test('buildDescription includes only subtitle when other fields are null', function () {
    $clip = createClipWithVideo([
        'scripture' => null,
        'subtitle' => 'Easter Sunday',
        'preacher' => null,
    ]);

    $result = $clip->buildDescription('Excerpt text.');

    expect($result)->toContain('Clip from a sermon for Easter Sunday.');
});

test('buildDescription appends call to action from settings', function () {
    Settings::instance()->update(['call_to_action' => 'Subscribe for more!']);

    $clip = createClipWithVideo();

    $result = $clip->buildDescription('Excerpt.');

    expect($result)->toContain('Subscribe for more!');
});

test('buildDescription omits call to action when not set', function () {
    $clip = createClipWithVideo();

    $result = $clip->buildDescription('Excerpt.');

    $lines = explode("\n", $result);

    expect($lines[0])->toBe('Excerpt.')
        ->and(end($lines))->not->toContain('Subscribe');
});

test('buildDescription lowercases leading article in subtitle', function () {
    $clip = createClipWithVideo([
        'subtitle' => 'The Resurrection',
        'scripture' => null,
        'preacher' => null,
    ]);

    $result = $clip->buildDescription('Excerpt.');

    expect($result)->toContain('for the Resurrection');
});
