<?php

use App\Jobs\ExtractPreviewFrame;
use App\Jobs\ScanSermonVideos;
use App\Models\SermonVideo;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('sermon_videos');
    Storage::fake('public');
});

test('job extracts preview frame successfully', function () {
    Process::fake(['*' => Process::result()]);

    $video = SermonVideo::factory()->create([
        'duration' => 600,
    ]);

    Storage::disk('sermon_videos')->put($video->raw_video_path, 'fake-content');

    (new ExtractPreviewFrame($video))->handle();

    $video->refresh();
    $expectedPath = 'frames/'.pathinfo($video->raw_video_path, PATHINFO_FILENAME).'.jpg';
    expect($video->preview_frame_path)->toBe($expectedPath);
});

test('job uses video midpoint for seek time', function () {
    Process::fake(['*' => Process::result()]);

    $video = SermonVideo::factory()->create([
        'duration' => 600,
    ]);

    Storage::disk('sermon_videos')->put($video->raw_video_path, 'fake-content');

    (new ExtractPreviewFrame($video))->handle();

    Process::assertRan(function ($process) {
        $command = $process->command;
        $ssIndex = array_search('-ss', $command);

        return $ssIndex !== false && $command[$ssIndex + 1] === '300';
    });
});

test('job handles missing duration gracefully', function () {
    Process::fake(['*' => Process::result()]);

    $video = SermonVideo::factory()->create([
        'duration' => null,
    ]);

    Storage::disk('sermon_videos')->put($video->raw_video_path, 'fake-content');

    (new ExtractPreviewFrame($video))->handle();

    Process::assertRan(function ($process) {
        $command = $process->command;
        $ssIndex = array_search('-ss', $command);

        return $ssIndex !== false && $command[$ssIndex + 1] === '0';
    });

    $video->refresh();
    expect($video->preview_frame_path)->not->toBeNull();
});

test('job handles ffmpeg failure gracefully', function () {
    Process::fake(['*' => Process::result(
        exitCode: 1,
        errorOutput: 'ffmpeg crashed',
    )]);

    $video = SermonVideo::factory()->create([
        'duration' => 600,
    ]);

    Storage::disk('sermon_videos')->put($video->raw_video_path, 'fake-content');

    (new ExtractPreviewFrame($video))->handle();

    $video->refresh();
    expect($video->preview_frame_path)->toBeNull();
});

test('job does not overwrite existing frame path on failure', function () {
    Process::fake(['*' => Process::result(
        exitCode: 1,
        errorOutput: 'ffmpeg crashed',
    )]);

    $video = SermonVideo::factory()->create([
        'duration' => 600,
        'preview_frame_path' => 'frames/existing.jpg',
    ]);

    Storage::disk('sermon_videos')->put($video->raw_video_path, 'fake-content');

    (new ExtractPreviewFrame($video))->handle();

    $video->refresh();
    expect($video->preview_frame_path)->toBe('frames/existing.jpg');
});

test('scan dispatches frame extraction for videos without frames', function () {
    Queue::fake();

    $videoWithFrame = SermonVideo::factory()->create([
        'preview_frame_path' => 'frames/existing.jpg',
    ]);

    $videoWithoutFrame = SermonVideo::factory()->create([
        'preview_frame_path' => null,
    ]);

    Storage::disk('sermon_videos')->put($videoWithFrame->raw_video_path, 'fake-content');
    Storage::disk('sermon_videos')->put($videoWithoutFrame->raw_video_path, 'fake-content');

    (new ScanSermonVideos(
        transcribe: false,
        convertToVertical: false,
        includeRecent: true,
    ))->handle(app(\App\Services\VideoProbe::class));

    Queue::assertPushed(ExtractPreviewFrame::class, function ($job) use ($videoWithoutFrame) {
        return $job->sermonVideo->id === $videoWithoutFrame->id;
    });

    Queue::assertNotPushed(ExtractPreviewFrame::class, function ($job) use ($videoWithFrame) {
        return $job->sermonVideo->id === $videoWithFrame->id;
    });
});
