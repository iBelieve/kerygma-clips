<?php

use App\Filament\Resources\SermonVideos\Pages\ListSermonVideos;
use App\Filament\Resources\SermonVideos\SermonVideoResource;
use App\Jobs\ScanSermonVideos;
use App\Models\SermonVideo;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('it can render the list page', function () {
    Livewire::test(ListSermonVideos::class)
        ->assertSuccessful();
});

test('it lists sermon videos', function () {
    $videos = [
        SermonVideo::create([
            'raw_video_path' => '2025-12-10 18-53-50.mp4',
            'date' => '2025-12-10 18:53:50',
            'duration' => 3600,
        ]),
        SermonVideo::create([
            'raw_video_path' => '2025-12-11 09-30-00.mp4',
            'date' => '2025-12-11 09:30:00',
            'title' => 'Sunday Service',
            'duration' => 5400,
        ]),
    ];

    Livewire::test(ListSermonVideos::class)
        ->assertCanSeeTableRecords($videos);
});

test('it dispatches scan job from header action', function () {
    Queue::fake();

    Livewire::test(ListSermonVideos::class)
        ->callAction('scan');

    Queue::assertPushed(ScanSermonVideos::class, function (ScanSermonVideos $job) {
        return $job->verbose === true;
    });
});

test('it cannot create sermon videos', function () {
    expect(SermonVideoResource::canCreate())->toBeFalse();
});
