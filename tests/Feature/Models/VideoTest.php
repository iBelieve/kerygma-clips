<?php

use App\Enums\VideoType;
use App\Models\Video;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('datetime properties return CarbonImmutable instances', function () {
    $video = Video::factory()->create([
        'transcription_started_at' => now(),
        'transcription_completed_at' => now(),
    ]);

    $video->refresh();

    expect($video->date)->toBeInstanceOf(CarbonImmutable::class);
    expect($video->transcription_started_at)->toBeInstanceOf(CarbonImmutable::class);
    expect($video->transcription_completed_at)->toBeInstanceOf(CarbonImmutable::class);
});

test('transcription_duration is computed from timestamps', function () {
    $video = Video::factory()->create([
        'transcription_started_at' => '2026-02-20 12:00:00',
        'transcription_completed_at' => '2026-02-20 12:05:30',
    ]);

    $video->refresh();

    expect($video->transcription_duration)->toBe(330);
});

test('transcription_duration is null when started_at is missing', function () {
    $video = Video::factory()->create([
        'transcription_started_at' => null,
        'transcription_completed_at' => now(),
    ]);

    $video->refresh();

    expect($video->transcription_duration)->toBeNull();
});

test('transcription_duration is null when completed_at is missing', function () {
    $video = Video::factory()->create([
        'transcription_started_at' => now(),
        'transcription_completed_at' => null,
    ]);

    $video->refresh();

    expect($video->transcription_duration)->toBeNull();
});

test('transcription_duration is null when both timestamps are missing', function () {
    $video = Video::factory()->create();

    $video->refresh();

    expect($video->transcription_duration)->toBeNull();
});

test('rawVideoDisk returns sermon_videos disk for sermon videos', function () {
    Storage::fake('sermon_videos');

    $video = Video::factory()->create(['type' => VideoType::Sermon]);

    expect($video->rawVideoDisk())->toBe(Storage::disk('sermon_videos'));
});

test('rawVideoDisk returns local disk for uploaded videos', function () {
    Storage::fake('local');

    $video = Video::factory()->create(['type' => VideoType::Upload]);

    expect($video->rawVideoDisk())->toBe(Storage::disk('local'));
});

test('deleting uploaded video removes raw file from local disk', function () {
    Storage::fake('local');
    Storage::fake('public');

    $video = Video::factory()->create([
        'type' => VideoType::Upload,
        'raw_video_path' => 'uploads/test-video.mp4',
    ]);

    Storage::disk('local')->put('uploads/test-video.mp4', 'fake-content');

    $video->delete();

    Storage::disk('local')->assertMissing('uploads/test-video.mp4');
});

test('deleting sermon video does not remove raw file', function () {
    Storage::fake('sermon_videos');
    Storage::fake('public');

    $video = Video::factory()->create(['type' => VideoType::Sermon]);

    Storage::disk('sermon_videos')->put($video->raw_video_path, 'fake-content');

    $video->delete();

    Storage::disk('sermon_videos')->assertExists($video->raw_video_path);
});
