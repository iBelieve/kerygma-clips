<?php

use App\Filament\Resources\SermonVideos\Pages\ViewSermonVideo;
use App\Jobs\ExtractSermonClipVerticalVideo;
use App\Models\SermonVideo;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Queue::fake();
});

/**
 * @return array{segments: list<array{start: float, end: float, text: string}>}
 */
function buildTranscript(int $count): array
{
    $segments = [];

    for ($i = 0; $i < $count; $i++) {
        $segments[] = [
            'start' => $i * 5.0,
            'end' => $i * 5.0 + 5.0,
            'text' => "Segment {$i}",
        ];
    }

    return ['segments' => $segments];
}

function buildSermonVideo(int $segmentCount = 30): SermonVideo
{
    return SermonVideo::create([
        'raw_video_path' => '2025-12-10 18-53-50.mp4',
        'date' => '2025-12-10 18:53:50',
        'duration' => $segmentCount * 5,
        'transcript' => buildTranscript($segmentCount),
    ]);
}

test('createClip dispatches ExtractSermonClipVerticalVideo job', function () {
    $video = buildSermonVideo();

    Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('createClip', 2, 5);

    Queue::assertPushed(ExtractSermonClipVerticalVideo::class, function ($job) use ($video) {
        return $job->sermonClip->sermon_video_id === $video->id
            && $job->sermonClip->start_segment_index === 2
            && $job->sermonClip->end_segment_index === 5;
    });
});

test('updateClip dispatches ExtractSermonClipVerticalVideo job', function () {
    $video = buildSermonVideo();
    $clip = $video->sermonClips()->create([
        'start_segment_index' => 2,
        'end_segment_index' => 5,
    ]);

    Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('updateClip', $clip->id, 3, 6);

    Queue::assertPushed(ExtractSermonClipVerticalVideo::class, function ($job) use ($clip) {
        return $job->sermonClip->id === $clip->id
            && $job->sermonClip->start_segment_index === 3
            && $job->sermonClip->end_segment_index === 6;
    });
});

test('createClip does not dispatch job when clip is rejected', function () {
    $video = buildSermonVideo();

    // Create a clip that covers segments 5-10
    $video->sermonClips()->create([
        'start_segment_index' => 5,
        'end_segment_index' => 10,
    ]);

    // Try to create a clip starting inside the existing one (rejected)
    Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('createClip', 7, 15);

    // Only the original clip creation dispatch, not the rejected one
    Queue::assertNotPushed(ExtractSermonClipVerticalVideo::class);
});

test('updateClip does not dispatch job when update is rejected due to overlap', function () {
    $video = buildSermonVideo();

    $clip1 = $video->sermonClips()->create([
        'start_segment_index' => 0,
        'end_segment_index' => 5,
    ]);

    $clip2 = $video->sermonClips()->create([
        'start_segment_index' => 10,
        'end_segment_index' => 15,
    ]);

    // Try to expand clip2 to overlap clip1 (rejected)
    Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('updateClip', $clip2->id, 4, 15);

    Queue::assertNotPushed(ExtractSermonClipVerticalVideo::class);
});
