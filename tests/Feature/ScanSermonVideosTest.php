<?php

use App\Models\SermonVideo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('sermon_videos');
});

function createOldVideoFile(string $filename): void
{
    Storage::disk('sermon_videos')->put($filename, 'fake-content');
    $path = Storage::disk('sermon_videos')->path($filename);
    touch($path, Carbon::now()->subMinutes(10)->timestamp);
}

test('it creates a sermon video entry for a valid video file', function () {
    createOldVideoFile('2025-12-10 18-53-50.m4v');

    $this->artisan('app:scan-sermon-videos')
        ->assertSuccessful();

    expect(SermonVideo::count())->toBe(1);

    $video = SermonVideo::first();
    expect($video->raw_video_path)->toBe('2025-12-10 18-53-50.m4v');
    expect($video->title)->toBeNull();
    expect($video->transcript_status)->toBe('pending');
    expect($video->date->format('Y-m-d H:i:s'))->toBe('2025-12-10 18:53:50');
});

test('it skips files that are too recently modified', function () {
    Storage::disk('sermon_videos')->put('2025-12-10 18-53-50.mp4', 'fake-content');

    $this->artisan('app:scan-sermon-videos')
        ->assertSuccessful();

    expect(SermonVideo::count())->toBe(0);
});

test('it skips files that already have a sermon video entry', function () {
    createOldVideoFile('2025-12-10 18-53-50.mp4');

    SermonVideo::create([
        'raw_video_path' => '2025-12-10 18-53-50.mp4',
        'date' => now(),
    ]);

    $this->artisan('app:scan-sermon-videos')
        ->assertSuccessful();

    expect(SermonVideo::count())->toBe(1);
});

test('it skips non-video files', function () {
    createOldVideoFile('2025-12-10 18-53-50.txt');
    createOldVideoFile('2025-12-10 18-53-50.jpg');

    $this->artisan('app:scan-sermon-videos')
        ->assertSuccessful();

    expect(SermonVideo::count())->toBe(0);
});

test('it skips video files with non-date filenames', function () {
    createOldVideoFile('random-sermon-title.mp4');

    $this->artisan('app:scan-sermon-videos')
        ->assertSuccessful();

    expect(SermonVideo::count())->toBe(0);
});

test('it processes multiple video files in a single run', function () {
    createOldVideoFile('2025-12-10 18-53-50.mp4');
    createOldVideoFile('2025-12-11 09-30-00.mov');
    createOldVideoFile('2025-12-12 14-00-00.mkv');

    $this->artisan('app:scan-sermon-videos')
        ->assertSuccessful();

    expect(SermonVideo::count())->toBe(3);
});

test('it reports no video files found when disk is empty and verbose', function () {
    $this->artisan('app:scan-sermon-videos --verbose')
        ->expectsOutput('No video files found on the sermon_videos disk.')
        ->assertSuccessful();
});
