<?php

use App\Filament\Resources\Videos\Pages\ListVideos;
use App\Filament\Resources\Videos\Pages\UploadVideo;
use App\Filament\Resources\Videos\VideoResource;
use App\Jobs\ScanSermonVideos;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('it can render the list page', function () {
    Livewire::test(ListVideos::class)
        ->assertSuccessful();
});

test('it lists sermon videos', function () {
    $videos = [
        Video::create([
            'raw_video_path' => '2025-12-10 18-53-50.mp4',
            'date' => '2025-12-10 18:53:50',
            'duration' => 3600,
        ]),
        Video::create([
            'raw_video_path' => '2025-12-11 09-30-00.mp4',
            'date' => '2025-12-11 09:30:00',
            'title' => 'Sunday Service',
            'duration' => 5400,
        ]),
    ];

    Livewire::test(ListVideos::class)
        ->assertCanSeeTableRecords($videos);
});

test('it dispatches scan job from header action', function () {
    Queue::fake();

    Livewire::test(ListVideos::class)
        ->callAction('scan');

    Queue::assertPushed(ScanSermonVideos::class, function (ScanSermonVideos $job) {
        return $job->verbose === true;
    });
});

test('it can create videos via upload', function () {
    expect(VideoResource::canCreate())->toBeTrue();
});

test('it can render the upload page', function () {
    Livewire::test(UploadVideo::class)
        ->assertSuccessful();
});
