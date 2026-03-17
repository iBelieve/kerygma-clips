<?php

use App\Enums\ClipStatus;
use App\Enums\JobStatus;
use App\Filament\Resources\VideoClips\Pages\EditVideoClip;
use App\Filament\Resources\VideoClips\Pages\ListVideoClips;
use App\Filament\Resources\VideoClips\VideoClipResource;
use App\Jobs\ExtractVideoClipVerticalVideo;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function createVideoWithTranscript(int $segmentCount = 10): Video
{
    $segments = [];
    for ($i = 0; $i < $segmentCount; $i++) {
        $segments[] = [
            'start' => $i * 5.0,
            'end' => $i * 5.0 + 5.0,
            'text' => "Segment {$i}",
        ];
    }

    return Video::factory()->create([
        'transcript' => ['segments' => $segments],
    ]);
}

test('it can render the list page', function () {
    Livewire::test(ListVideoClips::class)
        ->assertSuccessful();
});

test('it lists sermon clips', function () {
    $video = createVideoWithTranscript();

    $clips = [
        $video->videoClips()->create(['start_segment_index' => 0, 'end_segment_index' => 3]),
        $video->videoClips()->create(['start_segment_index' => 5, 'end_segment_index' => 8]),
    ];

    Livewire::test(ListVideoClips::class)
        ->assertCanSeeTableRecords($clips);
});

test('it dispatches extract job from header action', function () {
    Queue::fake();

    $video = createVideoWithTranscript();
    $video->videoClips()->create([
        'start_segment_index' => 0,
        'end_segment_index' => 3,
        'clip_video_status' => JobStatus::Pending,
    ]);

    Livewire::test(ListVideoClips::class)
        ->callAction('extract_all_videos');

    Queue::assertPushed(ExtractVideoClipVerticalVideo::class);
});

test('it cannot create sermon clips', function () {
    expect(VideoClipResource::canCreate())->toBeFalse();
});

test('it formats the start time and duration columns as h:mm:ss or m:ss', function () {
    $video = createVideoWithTranscript(segmentCount: 750);
    $video->update(['duration' => 3750]);

    // Segments have no gaps (each 5s long, back-to-back), so pause_before and pause_after are 0
    // between adjacent segments. starts_at = segment start, ends_at = segment end.

    // Clip spanning segments 720–749: starts_at=3600.0 (1:00:00), ends_at=3750.0, duration=150.0 (2:30)
    $clip1 = $video->videoClips()->create([
        'start_segment_index' => 720,
        'end_segment_index' => 749,
    ]);

    // Clip spanning segments 1–3: starts_at=5.0 (0:05), ends_at=20.0, duration=15.0 (0:15)
    $clip2 = $video->videoClips()->create([
        'start_segment_index' => 1,
        'end_segment_index' => 3,
    ]);

    // Refresh to load virtual column values computed by SQLite
    $clip1->refresh();
    $clip2->refresh();

    Livewire::test(ListVideoClips::class)
        ->assertTableColumnFormattedStateSet('starts_at', '1:00:00', $clip1)
        ->assertTableColumnFormattedStateSet('starts_at', '0:05', $clip2)
        ->assertTableColumnFormattedStateSet('duration', '2:30', $clip1)
        ->assertTableColumnFormattedStateSet('duration', '0:15', $clip2);
});

test('it can render the edit page', function () {
    $video = createVideoWithTranscript();
    $clip = $video->videoClips()->create([
        'start_segment_index' => 2,
        'end_segment_index' => 5,
        'title' => 'Grace and Mercy',
    ]);

    Livewire::test(EditVideoClip::class, ['record' => $clip->id])
        ->assertSuccessful()
        ->assertFormFieldExists('title')
        ->assertFormSet(['title' => 'Grace and Mercy']);
});

test('edit page shows transcript segments for the clip', function () {
    $video = createVideoWithTranscript();
    $clip = $video->videoClips()->create([
        'start_segment_index' => 2,
        'end_segment_index' => 4,
    ]);

    $component = Livewire::test(EditVideoClip::class, ['record' => $clip->id]);

    $rows = $component->instance()->transcriptRows;

    $segmentRows = array_filter($rows, fn (array $row) => $row['type'] === 'segment');
    expect($segmentRows)->toHaveCount(3);

    $texts = array_column($segmentRows, 'text');
    expect($texts)->toBe(['Segment 2', 'Segment 3', 'Segment 4']);
});

test('edit page can save the title', function () {
    $video = createVideoWithTranscript();
    $clip = $video->videoClips()->create([
        'start_segment_index' => 0,
        'end_segment_index' => 3,
        'title' => 'Old Title',
    ]);

    Livewire::test(EditVideoClip::class, ['record' => $clip->id])
        ->fillForm(['title' => 'New Title'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($clip->refresh()->title)->toBe('New Title');
});

test('getTranscriptText returns joined segment text', function () {
    $video = createVideoWithTranscript();
    $clip = $video->videoClips()->create([
        'start_segment_index' => 2,
        'end_segment_index' => 4,
    ]);

    expect($clip->getTranscriptText())->toBe('Segment 2 Segment 3 Segment 4');
});

test('excerpt is auto-populated on creation', function () {
    $video = createVideoWithTranscript();
    $clip = $video->videoClips()->create([
        'start_segment_index' => 1,
        'end_segment_index' => 3,
    ]);

    expect($clip->excerpt)->toBe('Segment 1 Segment 2 Segment 3');
});

test('excerpt is not overwritten on update', function () {
    $video = createVideoWithTranscript();
    $clip = $video->videoClips()->create([
        'start_segment_index' => 0,
        'end_segment_index' => 2,
    ]);

    $clip->update(['excerpt' => 'Custom excerpt']);

    Livewire::test(EditVideoClip::class, ['record' => $clip->id])
        ->fillForm(['title' => 'Updated Title'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($clip->refresh()->excerpt)->toBe('Custom excerpt');
});

test('edit page has excerpt field', function () {
    $video = createVideoWithTranscript();
    $clip = $video->videoClips()->create([
        'start_segment_index' => 0,
        'end_segment_index' => 3,
    ]);

    Livewire::test(EditVideoClip::class, ['record' => $clip->id])
        ->assertFormFieldExists('excerpt');
});

test('new clips default to draft status', function () {
    $video = createVideoWithTranscript();
    $clip = $video->videoClips()->create([
        'start_segment_index' => 0,
        'end_segment_index' => 3,
    ]);

    expect($clip->fresh()->status)->toBe(ClipStatus::Draft);
});

test('draft tab only shows draft clips', function () {
    $video = createVideoWithTranscript();
    $draftClip = $video->videoClips()->create([
        'start_segment_index' => 0,
        'end_segment_index' => 3,
        'status' => ClipStatus::Draft,
    ]);
    $approvedClip = $video->videoClips()->create([
        'start_segment_index' => 4,
        'end_segment_index' => 7,
        'status' => ClipStatus::Approved,
    ]);

    Livewire::test(ListVideoClips::class)
        ->set('activeTab', 'draft')
        ->assertCanSeeTableRecords([$draftClip])
        ->assertCanNotSeeTableRecords([$approvedClip]);
});

test('approved tab only shows approved clips', function () {
    $video = createVideoWithTranscript();
    $draftClip = $video->videoClips()->create([
        'start_segment_index' => 0,
        'end_segment_index' => 3,
        'status' => ClipStatus::Draft,
    ]);
    $approvedClip = $video->videoClips()->create([
        'start_segment_index' => 4,
        'end_segment_index' => 7,
        'status' => ClipStatus::Approved,
    ]);

    Livewire::test(ListVideoClips::class)
        ->set('activeTab', 'approved')
        ->assertCanSeeTableRecords([$approvedClip])
        ->assertCanNotSeeTableRecords([$draftClip]);
});

test('all tab shows both draft and approved clips', function () {
    $video = createVideoWithTranscript();
    $draftClip = $video->videoClips()->create([
        'start_segment_index' => 0,
        'end_segment_index' => 3,
        'status' => ClipStatus::Draft,
    ]);
    $approvedClip = $video->videoClips()->create([
        'start_segment_index' => 4,
        'end_segment_index' => 7,
        'status' => ClipStatus::Approved,
    ]);

    Livewire::test(ListVideoClips::class)
        ->set('activeTab', 'all')
        ->assertCanSeeTableRecords([$draftClip, $approvedClip]);
});

test('reset excerpt action restores transcript text', function () {
    $video = createVideoWithTranscript();
    $clip = $video->videoClips()->create([
        'start_segment_index' => 2,
        'end_segment_index' => 4,
    ]);

    $clip->update(['excerpt' => 'Custom text']);

    Livewire::test(EditVideoClip::class, ['record' => $clip->id])
        ->assertFormSet(['excerpt' => 'Custom text'])
        ->callFormComponentAction('excerpt', 'resetExcerpt')
        ->assertFormSet(['excerpt' => 'Segment 2 Segment 3 Segment 4']);
});
