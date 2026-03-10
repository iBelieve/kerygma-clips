<?php

use App\Enums\VideoType;
use App\Filament\Resources\Videos\Pages\CreateVideo;
use App\Jobs\ConvertToVerticalVideo;
use App\Jobs\ExtractPreviewFrame;
use App\Jobs\TranscribeVideo;
use App\Models\User;
use App\Models\Video;
use App\Services\VideoProbe;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    Storage::fake('local');
    Queue::fake();

    $this->mock(VideoProbe::class)
        ->shouldReceive('getDurationInSeconds')
        ->andReturn(120);
});

test('it can render the create page', function () {
    Livewire::test(CreateVideo::class)
        ->assertSuccessful();
});

test('it creates a video with uploaded file', function () {
    $file = UploadedFile::fake()->create('test-video.mp4', 1024, 'video/mp4');

    Livewire::test(CreateVideo::class)
        ->fillForm([
            'title' => 'My Video',
            'raw_video_path' => [$file],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $video = Video::first();

    expect($video)
        ->title->toBe('My Video')
        ->type->toBe(VideoType::Upload)
        ->duration->toBe(120);
});

test('it dispatches processing jobs after creation', function () {
    $file = UploadedFile::fake()->create('test-video.mp4', 1024, 'video/mp4');

    Livewire::test(CreateVideo::class)
        ->fillForm([
            'title' => 'My Video',
            'raw_video_path' => [$file],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $video = Video::first();

    Queue::assertPushed(TranscribeVideo::class, fn ($job) => $job->video->is($video));
    Queue::assertPushed(ConvertToVerticalVideo::class, fn ($job) => $job->video->is($video));
    Queue::assertPushed(ExtractPreviewFrame::class, fn ($job) => $job->video->is($video));
});

test('it requires a title', function () {
    $file = UploadedFile::fake()->create('test-video.mp4', 1024, 'video/mp4');

    Livewire::test(CreateVideo::class)
        ->fillForm([
            'title' => '',
            'raw_video_path' => [$file],
        ])
        ->call('create')
        ->assertHasFormErrors(['title' => 'required']);
});

test('it requires a video file', function () {
    Livewire::test(CreateVideo::class)
        ->fillForm([
            'title' => 'My Video',
            'raw_video_path' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['raw_video_path' => 'required']);
});

test('it sets the date to now', function () {
    $this->freezeTime();
    $file = UploadedFile::fake()->create('test-video.mp4', 1024, 'video/mp4');

    Livewire::test(CreateVideo::class)
        ->fillForm([
            'title' => 'My Video',
            'raw_video_path' => [$file],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Video::first()->date->toDateTimeString())
        ->toBe(now()->toDateTimeString());
});
