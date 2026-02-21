<?php

use App\Filament\Resources\SermonVideos\Pages\ViewSermonVideo;
use App\Models\SermonClip;
use App\Models\SermonVideo;
use App\Models\User;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function makeTranscriptSegments(int $count, float $segmentDuration = 5.0, float $gapDuration = 0.5): array
{
    $segments = [];
    $time = 0.0;

    for ($i = 0; $i < $count; $i++) {
        $segments[] = [
            'start' => $time,
            'end' => $time + $segmentDuration,
            'text' => "Segment $i text.",
        ];
        $time += $segmentDuration + $gapDuration;
    }

    return $segments;
}

test('it can resize a clip start boundary', function () {
    $video = SermonVideo::factory()->create([
        'transcript' => ['segments' => makeTranscriptSegments(10)],
    ]);

    $clip = SermonClip::factory()->create([
        'sermon_video_id' => $video->id,
        'start_segment_index' => 2,
        'end_segment_index' => 5,
    ]);

    Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('resizeClip', $clip->id, 1, 5);

    expect($clip->fresh())
        ->start_segment_index->toBe(1)
        ->end_segment_index->toBe(5);
});

test('it can resize a clip end boundary', function () {
    $video = SermonVideo::factory()->create([
        'transcript' => ['segments' => makeTranscriptSegments(10)],
    ]);

    $clip = SermonClip::factory()->create([
        'sermon_video_id' => $video->id,
        'start_segment_index' => 2,
        'end_segment_index' => 5,
    ]);

    Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('resizeClip', $clip->id, 2, 7);

    expect($clip->fresh())
        ->start_segment_index->toBe(2)
        ->end_segment_index->toBe(7);
});

test('it cannot resize a clip beyond 90 seconds', function () {
    // 20 segments * 5s each + gaps = well over 90s total
    $video = SermonVideo::factory()->create([
        'transcript' => ['segments' => makeTranscriptSegments(20)],
    ]);

    $clip = SermonClip::factory()->create([
        'sermon_video_id' => $video->id,
        'start_segment_index' => 0,
        'end_segment_index' => 5,
    ]);

    // Segments 0-19: segment 0 starts at 0.0, segment 19 ends at 19*5.5 + 5 = 109.5
    // That's over 90s, so the resize should be rejected
    Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('resizeClip', $clip->id, 0, 19);

    expect($clip->fresh())
        ->start_segment_index->toBe(0)
        ->end_segment_index->toBe(5);
});

test('it cannot resize a clip to overlap another clip', function () {
    $video = SermonVideo::factory()->create([
        'transcript' => ['segments' => makeTranscriptSegments(15)],
    ]);

    $clip1 = SermonClip::factory()->create([
        'sermon_video_id' => $video->id,
        'start_segment_index' => 0,
        'end_segment_index' => 4,
    ]);

    SermonClip::factory()->create([
        'sermon_video_id' => $video->id,
        'start_segment_index' => 6,
        'end_segment_index' => 10,
    ]);

    // Try to resize clip1 end into clip2's range
    Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('resizeClip', $clip1->id, 0, 7);

    expect($clip1->fresh())
        ->start_segment_index->toBe(0)
        ->end_segment_index->toBe(4);
});

test('it cannot resize a clip with invalid segment indices', function () {
    $video = SermonVideo::factory()->create([
        'transcript' => ['segments' => makeTranscriptSegments(10)],
    ]);

    $clip = SermonClip::factory()->create([
        'sermon_video_id' => $video->id,
        'start_segment_index' => 2,
        'end_segment_index' => 5,
    ]);

    Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('resizeClip', $clip->id, 2, 99);

    expect($clip->fresh())
        ->start_segment_index->toBe(2)
        ->end_segment_index->toBe(5);
});

test('transcript rows include clip identity data', function () {
    $video = SermonVideo::factory()->create([
        'transcript' => ['segments' => makeTranscriptSegments(5)],
    ]);

    $clip = SermonClip::factory()->create([
        'sermon_video_id' => $video->id,
        'start_segment_index' => 1,
        'end_segment_index' => 3,
    ]);

    $component = Livewire::test(ViewSermonVideo::class, ['record' => $video->id]);

    $rows = $component->instance()->transcriptRows;

    $segmentRows = collect($rows)->where('type', 'segment');

    // Rows in the clip should have clip identity
    $clipRow = $segmentRows->firstWhere('segmentIndex', 2);
    expect($clipRow)
        ->clipId->toBe($clip->id)
        ->clipStart->toBe(1)
        ->clipEnd->toBe(3);

    // Rows outside the clip should have null clip identity
    $nonClipRow = $segmentRows->firstWhere('segmentIndex', 0);
    expect($nonClipRow)
        ->clipId->toBeNull()
        ->clipStart->toBeNull()
        ->clipEnd->toBeNull();
});

test('it formats duration correctly', function () {
    $video = SermonVideo::factory()->create([
        'transcript' => ['segments' => makeTranscriptSegments(1)],
    ]);

    $component = Livewire::test(ViewSermonVideo::class, ['record' => $video->id]);
    $instance = $component->instance();

    expect($instance->formatDuration(0))->toBe('0:00');
    expect($instance->formatDuration(5))->toBe('0:05');
    expect($instance->formatDuration(45))->toBe('0:45');
    expect($instance->formatDuration(60))->toBe('1:00');
    expect($instance->formatDuration(90))->toBe('1:30');
    expect($instance->formatDuration(65.4))->toBe('1:05');
});

test('it swaps start and end when resizing with inverted indices', function () {
    $video = SermonVideo::factory()->create([
        'transcript' => ['segments' => makeTranscriptSegments(10)],
    ]);

    $clip = SermonClip::factory()->create([
        'sermon_video_id' => $video->id,
        'start_segment_index' => 2,
        'end_segment_index' => 5,
    ]);

    // Call with inverted indices — should swap and succeed
    Livewire::test(ViewSermonVideo::class, ['record' => $video->id])
        ->call('resizeClip', $clip->id, 5, 2);

    expect($clip->fresh())
        ->start_segment_index->toBe(2)
        ->end_segment_index->toBe(5);
});
