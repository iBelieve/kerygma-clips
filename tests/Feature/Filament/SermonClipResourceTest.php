<?php

use App\Enums\JobStatus;
use App\Filament\Resources\SermonClips\Pages\EditSermonClip;
use App\Filament\Resources\SermonClips\Pages\ListSermonClips;
use App\Filament\Resources\SermonClips\SermonClipResource;
use App\Jobs\ExtractSermonClipVerticalVideo;
use App\Models\SermonVideo;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function createVideoWithTranscript(int $segmentCount = 10): SermonVideo
{
    $segments = [];
    for ($i = 0; $i < $segmentCount; $i++) {
        $segments[] = [
            'start' => $i * 5.0,
            'end' => $i * 5.0 + 5.0,
            'text' => "Segment {$i}",
        ];
    }

    return SermonVideo::factory()->create([
        'transcript' => ['segments' => $segments],
    ]);
}

test('it can render the list page', function () {
    Livewire::test(ListSermonClips::class)
        ->assertSuccessful();
});

test('it lists sermon clips', function () {
    $video = createVideoWithTranscript();

    $clips = [
        $video->sermonClips()->create(['start_segment_index' => 0, 'end_segment_index' => 3]),
        $video->sermonClips()->create(['start_segment_index' => 5, 'end_segment_index' => 8]),
    ];

    Livewire::test(ListSermonClips::class)
        ->assertCanSeeTableRecords($clips);
});

test('it dispatches extract job from header action', function () {
    Queue::fake();

    $video = createVideoWithTranscript();
    $video->sermonClips()->create([
        'start_segment_index' => 0,
        'end_segment_index' => 3,
        'clip_video_status' => JobStatus::Pending,
    ]);

    Livewire::test(ListSermonClips::class)
        ->callAction('extract_all_videos');

    Queue::assertPushed(ExtractSermonClipVerticalVideo::class);
});

test('it cannot create sermon clips', function () {
    expect(SermonClipResource::canCreate())->toBeFalse();
});

test('it can render the edit page', function () {
    $video = createVideoWithTranscript();
    $clip = $video->sermonClips()->create([
        'start_segment_index' => 2,
        'end_segment_index' => 5,
        'title' => 'Grace and Mercy',
    ]);

    Livewire::test(EditSermonClip::class, ['record' => $clip->id])
        ->assertSuccessful()
        ->assertFormFieldExists('title')
        ->assertFormSet(['title' => 'Grace and Mercy']);
});

test('edit page shows transcript segments for the clip', function () {
    $video = createVideoWithTranscript();
    $clip = $video->sermonClips()->create([
        'start_segment_index' => 2,
        'end_segment_index' => 4,
    ]);

    $component = Livewire::test(EditSermonClip::class, ['record' => $clip->id]);

    $rows = $component->instance()->transcriptRows;

    $segmentRows = array_filter($rows, fn (array $row) => $row['type'] === 'segment');
    expect($segmentRows)->toHaveCount(3);

    $texts = array_column($segmentRows, 'text');
    expect($texts)->toBe(['Segment 2', 'Segment 3', 'Segment 4']);
});

test('edit page can save the title', function () {
    $video = createVideoWithTranscript();
    $clip = $video->sermonClips()->create([
        'start_segment_index' => 0,
        'end_segment_index' => 3,
        'title' => 'Old Title',
    ]);

    Livewire::test(EditSermonClip::class, ['record' => $clip->id])
        ->fillForm(['title' => 'New Title'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($clip->refresh()->title)->toBe('New Title');
});
