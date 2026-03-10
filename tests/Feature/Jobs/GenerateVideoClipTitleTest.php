<?php

use App\Ai\Agents\VideoClipTitleGenerator;
use App\Filament\Resources\Videos\Pages\EditVideo;
use App\Jobs\GenerateVideoClipTitle;
use App\Models\User;
use App\Models\Video;
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

function buildTitleTestVideo(int $segmentCount = 30, ?string $title = null): Video
{
    return Video::create([
        'raw_video_path' => '2025-12-10 18-53-50.mp4',
        'date' => '2025-12-10 18:53:50',
        'duration' => $segmentCount * 5,
        'transcript' => buildTitleTestTranscript($segmentCount),
        'title' => $title,
    ]);
}

// --- Job tests ---

test('job generates title and saves it to the clip', function () {
    VideoClipTitleGenerator::fake(['Grace and Mercy in Action']);

    $video = buildTitleTestVideo();
    $clip = $video->videoClips()->create([
        'start_segment_index' => 2,
        'end_segment_index' => 5,
    ]);

    GenerateVideoClipTitle::dispatchSync($clip);

    $clip->refresh();
    expect($clip->title)->toBe('Grace and Mercy in Action');
});

test('job includes sermon title in prompt when available', function () {
    VideoClipTitleGenerator::fake(['The Power of Grace']);

    $video = buildTitleTestVideo(title: 'Sunday Morning Service');
    $clip = $video->videoClips()->create([
        'start_segment_index' => 0,
        'end_segment_index' => 2,
    ]);

    GenerateVideoClipTitle::dispatchSync($clip);

    VideoClipTitleGenerator::assertPrompted(function ($prompt) {
        return str_contains($prompt->prompt, 'Sermon: Sunday Morning Service')
            && str_contains($prompt->prompt, 'Transcript:');
    });
});

test('job sends transcript text from correct segment range', function () {
    VideoClipTitleGenerator::fake(['Test Title']);

    $video = buildTitleTestVideo();
    $clip = $video->videoClips()->create([
        'start_segment_index' => 2,
        'end_segment_index' => 4,
    ]);

    GenerateVideoClipTitle::dispatchSync($clip);

    VideoClipTitleGenerator::assertPrompted(function ($prompt) {
        return str_contains($prompt->prompt, 'Segment 2 text')
            && str_contains($prompt->prompt, 'Segment 3 text')
            && str_contains($prompt->prompt, 'Segment 4 text')
            && ! str_contains($prompt->prompt, 'Segment 1 text')
            && ! str_contains($prompt->prompt, 'Segment 5 text');
    });
});

test('job does not call agent when transcript text is empty', function () {
    VideoClipTitleGenerator::fake(['Should not be called']);

    $video = Video::create([
        'raw_video_path' => '2025-12-10 18-53-50.mp4',
        'date' => '2025-12-10 18:53:50',
        'duration' => 100,
        'transcript' => ['segments' => [
            ['start' => 0.0, 'end' => 5.0, 'text' => ''],
            ['start' => 5.0, 'end' => 10.0, 'text' => '  '],
        ]],
    ]);

    $clip = $video->videoClips()->create([
        'start_segment_index' => 0,
        'end_segment_index' => 1,
    ]);

    GenerateVideoClipTitle::dispatchSync($clip);

    $clip->refresh();
    expect($clip->title)->toBeNull();
    VideoClipTitleGenerator::assertNeverPrompted();
});

// --- Dispatch tests ---

test('creating a clip dispatches GenerateVideoClipTitle', function () {
    Queue::fake([GenerateVideoClipTitle::class]);
    $this->actingAs(User::factory()->create());

    $video = buildTitleTestVideo();

    Livewire::test(EditVideo::class, ['record' => $video->id])
        ->call('createClip', 2, 5);

    Queue::assertPushed(GenerateVideoClipTitle::class, function ($job) {
        return $job->videoClip->start_segment_index === 2
            && $job->videoClip->end_segment_index === 5;
    });
});

test('updating a clip with changed boundaries dispatches GenerateVideoClipTitle', function () {
    Queue::fake([GenerateVideoClipTitle::class]);
    $this->actingAs(User::factory()->create());

    $video = buildTitleTestVideo();
    $clip = $video->videoClips()->create([
        'start_segment_index' => 2,
        'end_segment_index' => 5,
    ]);

    Livewire::test(EditVideo::class, ['record' => $video->id])
        ->call('updateClip', $clip->id, 3, 6);

    Queue::assertPushed(GenerateVideoClipTitle::class);
});

test('updating a clip without changing boundaries does not dispatch GenerateVideoClipTitle', function () {
    Queue::fake([GenerateVideoClipTitle::class]);
    $this->actingAs(User::factory()->create());

    $video = buildTitleTestVideo();
    $clip = $video->videoClips()->create([
        'start_segment_index' => 2,
        'end_segment_index' => 5,
    ]);

    Livewire::test(EditVideo::class, ['record' => $video->id])
        ->call('updateClip', $clip->id, 2, 5);

    Queue::assertNotPushed(GenerateVideoClipTitle::class);
});
