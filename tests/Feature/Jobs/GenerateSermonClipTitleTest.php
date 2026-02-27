<?php

use App\Ai\Agents\SermonClipTitleGenerator;
use App\Filament\Resources\SermonVideos\Pages\ViewSermonVideo;
use App\Jobs\GenerateSermonClipTitle;
use App\Models\SermonVideo;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Build a transcript with evenly spaced segments.
 *
 * Each segment is 5 seconds long starting at 0.
 *
 * @return array{segments: list<array{start: float, end: float, text: string}>}
 */
function buildTitleTestTranscript(int $count): array
{
    $segments = [];

    for ($i = 0; $i < $count; $i++) {
        $segments[] = [
            'start' => $i * 5.0,
            'end' => $i * 5.0 + 5.0,
            'text' => "Segment {$i} text about grace and mercy",
        ];
    }

    return ['segments' => $segments];
}

function buildTitleTestSermonVideo(int $segmentCount = 30, ?string $title = null): SermonVideo
{
    return SermonVideo::create([
        'raw_video_path' => '2025-12-10 18-53-50.mp4',
        'date' => '2025-12-10 18:53:50',
        'duration' => $segmentCount * 5,
        'transcript' => buildTitleTestTranscript($segmentCount),
        'title' => $title,
    ]);
}

// --- Job tests ---

test('job generates title and saves it to the clip', function () {
    SermonClipTitleGenerator::fake(['Grace and Mercy in Action']);

    $video = buildTitleTestSermonVideo();
    $clip = $video->sermonClips()->create([
        'start_segment_index' => 2,
        'end_segment_index' => 5,
    ]);

    GenerateSermonClipTitle::dispatchSync($clip);

    $clip->refresh();
    expect($clip->title)->toBe('Grace and Mercy in Action');
});

test('job includes sermon title in prompt when available', function () {
    SermonClipTitleGenerator::fake(['The Power of Grace']);

    $video = buildTitleTestSermonVideo(title: 'Sunday Morning Service');
    $clip = $video->sermonClips()->create([
        'start_segment_index' => 0,
        'end_segment_index' => 2,
    ]);

    GenerateSermonClipTitle::dispatchSync($clip);

    SermonClipTitleGenerator::assertPrompted(function ($prompt) {
        return str_contains($prompt->prompt, 'Sermon: Sunday Morning Service')
            && str_contains($prompt->prompt, 'Transcript:');
    });
});

test('job sends transcript text from correct segment range', function () {
    SermonClipTitleGenerator::fake(['Test Title']);

    $video = buildTitleTestSermonVideo();
    $clip = $video->sermonClips()->create([
        'start_segment_index' => 2,
        'end_segment_index' => 4,
    ]);

    GenerateSermonClipTitle::dispatchSync($clip);

    SermonClipTitleGenerator::assertPrompted(function ($prompt) {
        return str_contains($prompt->prompt, 'Segment 2 text')
            && str_contains($prompt->prompt, 'Segment 3 text')
            && str_contains($prompt->prompt, 'Segment 4 text')
            && ! str_contains($prompt->prompt, 'Segment 1 text')
            && ! str_contains($prompt->prompt, 'Segment 5 text');
    });
});

test('job does not call agent when transcript text is empty', function () {
    SermonClipTitleGenerator::fake(['Should not be called']);

    $video = SermonVideo::create([
        'raw_video_path' => '2025-12-10 18-53-50.mp4',
        'date' => '2025-12-10 18:53:50',
        'duration' => 100,
        'transcript' => ['segments' => [
            ['start' => 0.0, 'end' => 5.0, 'text' => ''],
            ['start' => 5.0, 'end' => 10.0, 'text' => '  '],
        ]],
    ]);

    $clip = $video->sermonClips()->create([
        'start_segment_index' => 0,
        'end_segment_index' => 1,
    ]);

    GenerateSermonClipTitle::dispatchSync($clip);

    $clip->refresh();
    expect($clip->title)->toBeNull();
    SermonClipTitleGenerator::assertNeverPrompted();
});

// --- Dispatch tests ---

test('creating a clip dispatches GenerateSermonClipTitle', function () {
    Queue::fake([GenerateSermonClipTitle::class]);
    $this->actingAs(User::factory()->create());

    $video = buildTitleTestSermonVideo();

    Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('createClip', 2, 5);

    Queue::assertPushed(GenerateSermonClipTitle::class, function ($job) {
        return $job->sermonClip->start_segment_index === 2
            && $job->sermonClip->end_segment_index === 5;
    });
});

test('updating a clip with changed boundaries dispatches GenerateSermonClipTitle', function () {
    Queue::fake([GenerateSermonClipTitle::class]);
    $this->actingAs(User::factory()->create());

    $video = buildTitleTestSermonVideo();
    $clip = $video->sermonClips()->create([
        'start_segment_index' => 2,
        'end_segment_index' => 5,
    ]);

    Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('updateClip', $clip->id, 3, 6);

    Queue::assertPushed(GenerateSermonClipTitle::class);
});

test('updating a clip without changing boundaries does not dispatch GenerateSermonClipTitle', function () {
    Queue::fake([GenerateSermonClipTitle::class]);
    $this->actingAs(User::factory()->create());

    $video = buildTitleTestSermonVideo();
    $clip = $video->sermonClips()->create([
        'start_segment_index' => 2,
        'end_segment_index' => 5,
    ]);

    Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('updateClip', $clip->id, 2, 5);

    Queue::assertNotPushed(GenerateSermonClipTitle::class);
});
