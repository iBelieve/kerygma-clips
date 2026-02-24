<?php

use App\Filament\Resources\SermonVideos\Pages\ViewSermonVideo;
use App\Models\SermonVideo;
use App\Models\User;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * Build a transcript with evenly spaced segments.
 *
 * Each segment is 5 seconds long starting at 0.
 *
 * @return array{segments: list<array{start: float, end: float, text: string}>}
 */
function makeTranscript(int $count): array
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

function makeSermonVideo(int $segmentCount = 30): SermonVideo
{
    return SermonVideo::create([
        'raw_video_path' => '2025-12-10 18-53-50.mp4',
        'date' => '2025-12-10 18:53:50',
        'duration' => $segmentCount * 5,
        'transcript' => makeTranscript($segmentCount),
    ]);
}

// --- createClip tests ---

test('createClip truncates end to avoid overlapping a following clip', function () {
    $video = makeSermonVideo(30);
    // Existing clip at segments 10-12
    $video->sermonClips()->create([
        'start_segment_index' => 10,
        'end_segment_index' => 12,
    ]);

    $clips = Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('createClip', 5, 15)
        ->get('transcriptData')['clips'];

    // The new clip should be truncated to end at 9 (one before existing clip start)
    expect($clips)->toHaveCount(2);

    $newClip = collect($clips)->firstWhere('start', 5);
    expect($newClip)
        ->start->toBe(5)
        ->end->toBe(9);
});

test('createClip rejects when start is inside an existing clip', function () {
    $video = makeSermonVideo(30);
    $video->sermonClips()->create([
        'start_segment_index' => 5,
        'end_segment_index' => 10,
    ]);

    $clips = Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('createClip', 7, 15)
        ->get('transcriptData')['clips'];

    // Only the original clip should exist
    expect($clips)->toHaveCount(1);
    expect($clips[0])
        ->start->toBe(5)
        ->end->toBe(10);
});

test('createClip rejects when duration exceeds 90 seconds', function () {
    $video = makeSermonVideo(30);

    $clips = Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('createClip', 0, 29)
        ->get('transcriptData')['clips'];

    // 30 segments × 5s = 150s > 90s — should be rejected
    expect($clips)->toHaveCount(0);
});

test('createClip rejects when segment indices are out of bounds', function () {
    $video = makeSermonVideo(10);

    $clips = Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('createClip', 5, 20)
        ->get('transcriptData')['clips'];

    expect($clips)->toHaveCount(0);
});

test('createClip swaps start and end when reversed', function () {
    $video = makeSermonVideo(30);

    $clips = Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('createClip', 5, 2)
        ->get('transcriptData')['clips'];

    expect($clips)->toHaveCount(1);
    expect($clips[0])
        ->start->toBe(2)
        ->end->toBe(5);
});

test('createClip can create a clip between two existing clips', function () {
    $video = makeSermonVideo(30);

    // Clip at 0-3 (20s)
    $video->sermonClips()->create([
        'start_segment_index' => 0,
        'end_segment_index' => 3,
    ]);

    // Clip at 10-13 (20s)
    $video->sermonClips()->create([
        'start_segment_index' => 10,
        'end_segment_index' => 13,
    ]);

    // Try to create clip at 5-12 — should truncate end to 9
    $clips = Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('createClip', 5, 12)
        ->get('transcriptData')['clips'];

    expect($clips)->toHaveCount(3);

    $middleClip = collect($clips)->firstWhere('start', 5);
    expect($middleClip)
        ->start->toBe(5)
        ->end->toBe(9);
});

// --- updateClip tests ---

test('updateClip rejects when it would overlap another clip', function () {
    $video = makeSermonVideo(30);

    $clip1 = $video->sermonClips()->create([
        'start_segment_index' => 0,
        'end_segment_index' => 5,
    ]);

    $clip2 = $video->sermonClips()->create([
        'start_segment_index' => 10,
        'end_segment_index' => 15,
    ]);

    // Try to expand clip2 to overlap clip1
    $clips = Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('updateClip', $clip2->id, 4, 15)
        ->get('transcriptData')['clips'];

    // clip2 should remain unchanged
    $updated = collect($clips)->firstWhere('id', $clip2->id);
    expect($updated)
        ->start->toBe(10)
        ->end->toBe(15);
});

test('updateClip rejects when duration exceeds 90 seconds', function () {
    $video = makeSermonVideo(30);

    $clip = $video->sermonClips()->create([
        'start_segment_index' => 5,
        'end_segment_index' => 8,
    ]);

    // Try to expand to 0-29 (150s)
    $clips = Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('updateClip', $clip->id, 0, 29)
        ->get('transcriptData')['clips'];

    // Clip should remain unchanged
    $updated = collect($clips)->firstWhere('id', $clip->id);
    expect($updated)
        ->start->toBe(5)
        ->end->toBe(8);
});

test('updateClip rejects when segment indices are out of bounds', function () {
    $video = makeSermonVideo(10);

    $clip = $video->sermonClips()->create([
        'start_segment_index' => 2,
        'end_segment_index' => 5,
    ]);

    $clips = Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('updateClip', $clip->id, 2, 20)
        ->get('transcriptData')['clips'];

    // Clip should remain unchanged
    $updated = collect($clips)->firstWhere('id', $clip->id);
    expect($updated)
        ->start->toBe(2)
        ->end->toBe(5);
});

test('updateClip can shrink a clip without issues', function () {
    $video = makeSermonVideo(30);

    $clip = $video->sermonClips()->create([
        'start_segment_index' => 5,
        'end_segment_index' => 15,
    ]);

    $clips = Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('updateClip', $clip->id, 7, 12)
        ->get('transcriptData')['clips'];

    $updated = collect($clips)->firstWhere('id', $clip->id);
    expect($updated)
        ->start->toBe(7)
        ->end->toBe(12);
});

test('updateClip can expand toward a neighboring clip without overlapping', function () {
    $video = makeSermonVideo(30);

    $clip1 = $video->sermonClips()->create([
        'start_segment_index' => 0,
        'end_segment_index' => 5,
    ]);

    $clip2 = $video->sermonClips()->create([
        'start_segment_index' => 10,
        'end_segment_index' => 15,
    ]);

    // Expand clip1 end to 9 (adjacent but not overlapping)
    $clips = Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('updateClip', $clip1->id, 0, 9)
        ->get('transcriptData')['clips'];

    $updated = collect($clips)->firstWhere('id', $clip1->id);
    expect($updated)
        ->start->toBe(0)
        ->end->toBe(9);
});
