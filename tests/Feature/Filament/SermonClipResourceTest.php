<?php

use App\Enums\JobStatus;
use App\Filament\Resources\SermonClips\Pages\ListSermonClips;
use App\Filament\Resources\SermonClips\SermonClipResource;
use App\Jobs\ExtractSermonClipVerticalVideo;
use App\Models\SermonClip;
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
