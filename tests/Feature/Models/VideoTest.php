<?php

use App\Models\Video;
use Carbon\CarbonImmutable;

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
