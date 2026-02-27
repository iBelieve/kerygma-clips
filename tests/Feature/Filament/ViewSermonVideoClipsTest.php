<?php

use App\Filament\Resources\SermonVideos\Pages\ViewSermonVideo;
use App\Jobs\GenerateSermonClipTitle;
use App\Models\SermonClip;
use App\Models\SermonVideo;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Queue::fake([GenerateSermonClipTitle::class]);
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

// --- timestamp tests ---

test('createClip sets starts_at, ends_at, and duration from segment times', function () {
    $video = makeSermonVideo(30); // segments: 0–5s, 5–10s, … each 5s

    Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('createClip', 2, 5);

    $clip = SermonClip::where('sermon_video_id', $video->id)->sole();
    // Segment 2 starts at 10.0, segment 5 ends at 30.0
    expect($clip)
        ->starts_at->toBe(10.0)
        ->ends_at->toBe(30.0)
        ->duration->toBe(20.0);
});

test('updateClip updates starts_at, ends_at, and duration from segment times', function () {
    $video = makeSermonVideo(30);

    $clip = $video->sermonClips()->create([
        'start_segment_index' => 2,
        'end_segment_index' => 5,
    ]);

    // Shrink to segments 3–4
    Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('updateClip', $clip->id, 3, 4);

    $clip->refresh();
    // Segment 3 starts at 15.0, segment 4 ends at 25.0
    expect($clip)
        ->starts_at->toBe(15.0)
        ->ends_at->toBe(25.0)
        ->duration->toBe(10.0);
});

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

// --- pause_before / pause_after tests ---

/**
 * Build a transcript with gaps between segments.
 *
 * Each segment is 4 seconds long with a configurable gap between them.
 *
 * @return array{segments: list<array{start: float, end: float, text: string}>}
 */
function makeGappedTranscript(int $count, float $gap = 2.0): array
{
    $segments = [];
    $stride = 4.0 + $gap;

    for ($i = 0; $i < $count; $i++) {
        $segments[] = [
            'start' => $i * $stride,
            'end' => $i * $stride + 4.0,
            'text' => "Segment {$i}",
        ];
    }

    return ['segments' => $segments];
}

function makeGappedSermonVideo(int $segmentCount = 10, float $gap = 2.0): SermonVideo
{
    $stride = 4.0 + $gap;

    return SermonVideo::create([
        'raw_video_path' => '2025-12-10 18-53-50.mp4',
        'date' => '2025-12-10 18:53:50',
        'duration' => $segmentCount * $stride + 10,
        'transcript' => makeGappedTranscript($segmentCount, $gap),
    ]);
}

test('createClip calculates pause_before and pause_after from segment gaps', function () {
    // Segments with 2s gaps: [0-4], [6-10], [12-16], [18-22], [24-28]
    $video = makeGappedSermonVideo(5, 2.0);

    Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('createClip', 1, 3);

    $clip = SermonClip::where('sermon_video_id', $video->id)->sole();
    // Segment 1: start=6.0, Segment 3: end=22.0
    // Gap before: 6.0 - 4.0 = 2.0 → pause_before = 0.25 (capped at 0.25s)
    // Gap after: 24.0 - 22.0 = 2.0 → pause_after = 0.5 (capped at 0.5s)
    expect($clip)
        ->pause_before->toBe(0.25)
        ->pause_after->toBe(0.5)
        ->starts_at->toBe(5.75)
        ->ends_at->toBe(22.5)
        ->duration->toBe(16.75);
});

test('createClip calculates partial pause when gap is less than 2s', function () {
    // Segments with 1s gaps: [0-4], [5-9], [10-14], [15-19], [20-24]
    $video = makeGappedSermonVideo(5, 1.0);

    Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('createClip', 1, 3);

    $clip = SermonClip::where('sermon_video_id', $video->id)->sole();
    // Segment 1: start=5.0, Segment 3: end=19.0
    // Gap before: 5.0 - 4.0 = 1.0 → pause_before = 0.25 (capped at 0.25s)
    // Gap after: 20.0 - 19.0 = 1.0 → pause_after = 0.5
    expect($clip)
        ->pause_before->toBe(0.25)
        ->pause_after->toBe(0.5)
        ->starts_at->toBe(4.75)
        ->ends_at->toBe(19.5)
        ->duration->toBe(14.75);
});

test('createClip sets zero pause when segments are contiguous', function () {
    $video = makeSermonVideo(30); // contiguous segments, no gaps

    Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('createClip', 2, 5);

    $clip = SermonClip::where('sermon_video_id', $video->id)->sole();
    // Contiguous segments: gap = 0 on both sides
    expect($clip)
        ->pause_before->toBe(0.0)
        ->pause_after->toBe(0.0)
        ->starts_at->toBe(10.0)
        ->ends_at->toBe(30.0);
});

test('createClip calculates pause_before from start of video for first segment', function () {
    // Segments with 2s gaps, but first segment starts at 0.0
    $video = makeGappedSermonVideo(5, 2.0);

    Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('createClip', 0, 1);

    $clip = SermonClip::where('sermon_video_id', $video->id)->sole();
    // Segment 0: start=0.0 → gap from video start = 0.0 → pause_before = 0.0
    // Gap after: 12.0 - 10.0 = 2.0 → pause_after = 0.5 (capped at 0.5s)
    expect($clip)
        ->pause_before->toBe(0.0)
        ->pause_after->toBe(0.5);
});

test('createClip calculates pause_after from video duration for last segment', function () {
    // Segments with 2s gaps: [0-4], [6-10], [12-16], [18-22], [24-28]
    // Video duration = 5 * 6 + 10 = 40
    $video = makeGappedSermonVideo(5, 2.0);

    Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('createClip', 3, 4);

    $clip = SermonClip::where('sermon_video_id', $video->id)->sole();
    // Segment 4 is last segment: end=28.0, video duration=40.0
    // Gap after: 40.0 - 28.0 = 12.0 → pause_after = 0.5 (capped at 0.5s)
    expect($clip)
        ->pause_after->toBe(0.5);
});

test('updateClip recalculates pause values', function () {
    $video = makeGappedSermonVideo(10, 2.0);

    $clip = $video->sermonClips()->create([
        'start_segment_index' => 1,
        'end_segment_index' => 3,
    ]);

    expect($clip)
        ->pause_before->toBe(0.25)
        ->pause_after->toBe(0.5);

    // Move clip to segments 2-4
    Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('updateClip', $clip->id, 2, 4);

    $clip->refresh();
    // Gaps are still 2s → pauses still capped at 0.25s before, 0.5s after
    expect($clip)
        ->pause_before->toBe(0.25)
        ->pause_after->toBe(0.5)
        ->starts_at->toBe(11.75)
        ->ends_at->toBe(28.5);
});
