<?php

use App\Enums\JobStatus;
use App\Jobs\ExtractSermonClipVerticalVideo;
use App\Models\SermonClip;
use App\Models\SermonVideo;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

function createVideoWithVerticalAndTranscript(int $segmentCount = 10): SermonVideo
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
        'vertical_video_status' => JobStatus::Completed,
        'vertical_video_path' => 'vertical/test-video.mp4',
        'transcript' => ['segments' => $segments],
    ]);
}

test('job extracts clip from vertical video successfully', function () {
    Process::fake(['*' => Process::result()]);

    $video = createVideoWithVerticalAndTranscript();
    Storage::disk('public')->put($video->vertical_video_path, 'fake-vertical-content');

    $clip = SermonClip::factory()->create([
        'sermon_video_id' => $video->id,
        'start_segment_index' => 2,
        'end_segment_index' => 5,
    ]);

    (new ExtractSermonClipVerticalVideo($clip))->handle();

    $clip->refresh();
    expect($clip->clip_video_status)->toBe(JobStatus::Completed);
    expect($clip->clip_video_path)->toBe("clips/{$clip->id}.mp4");
    expect($clip->clip_video_error)->toBeNull();
});

test('job passes correct time range to ffmpeg', function () {
    Process::fake(['*' => Process::result()]);

    $video = createVideoWithVerticalAndTranscript();
    Storage::disk('public')->put($video->vertical_video_path, 'fake-vertical-content');

    // Segments 2-5: start=10.0, end=30.0, duration=20.0
    $clip = SermonClip::factory()->create([
        'sermon_video_id' => $video->id,
        'start_segment_index' => 2,
        'end_segment_index' => 5,
    ]);

    (new ExtractSermonClipVerticalVideo($clip))->handle();

    Process::assertRan(function ($process) {
        $command = $process->command;
        $ssIndex = array_search('-ss', $command);
        $tIndex = array_search('-t', $command);

        return $ssIndex !== false
            && $command[$ssIndex + 1] === '10'
            && $tIndex !== false
            && $command[$tIndex + 1] === '20';
    });
});

test('job fails when vertical video is not completed', function () {
    $video = SermonVideo::factory()->create([
        'vertical_video_status' => JobStatus::Pending,
        'transcript' => ['segments' => [
            ['start' => 0.0, 'end' => 5.0, 'text' => 'test'],
        ]],
    ]);

    $clip = SermonClip::factory()->create([
        'sermon_video_id' => $video->id,
        'start_segment_index' => 0,
        'end_segment_index' => 0,
    ]);

    (new ExtractSermonClipVerticalVideo($clip))->handle();

    $clip->refresh();
    expect($clip->clip_video_status)->toBe(JobStatus::Failed);
    expect($clip->clip_video_error)->toContain('does not have a completed vertical video');
});

test('job fails when segment indices are out of bounds', function () {
    $video = createVideoWithVerticalAndTranscript(5);
    Storage::disk('public')->put($video->vertical_video_path, 'fake-vertical-content');

    $clip = SermonClip::factory()->create([
        'sermon_video_id' => $video->id,
        'start_segment_index' => 2,
        'end_segment_index' => 10,
    ]);

    (new ExtractSermonClipVerticalVideo($clip))->handle();

    $clip->refresh();
    expect($clip->clip_video_status)->toBe(JobStatus::Failed);
    expect($clip->clip_video_error)->toContain('out of bounds');
});

test('job fails when ffmpeg fails', function () {
    Process::fake(['*' => Process::result(
        exitCode: 1,
        errorOutput: 'ffmpeg extraction failed',
    )]);

    $video = createVideoWithVerticalAndTranscript();
    Storage::disk('public')->put($video->vertical_video_path, 'fake-vertical-content');

    $clip = SermonClip::factory()->create([
        'sermon_video_id' => $video->id,
        'start_segment_index' => 0,
        'end_segment_index' => 3,
    ]);

    (new ExtractSermonClipVerticalVideo($clip))->handle();

    $clip->refresh();
    expect($clip->clip_video_status)->toBe(JobStatus::Failed);
    expect($clip->clip_video_error)->toContain('ffmpeg extraction failed');
});

test('job sets status to processing before starting', function () {
    Process::fake(['*' => Process::result()]);

    $video = createVideoWithVerticalAndTranscript();
    Storage::disk('public')->put($video->vertical_video_path, 'fake-vertical-content');

    $clip = SermonClip::factory()->create([
        'sermon_video_id' => $video->id,
        'start_segment_index' => 0,
        'end_segment_index' => 3,
        'clip_video_status' => JobStatus::Failed,
        'clip_video_error' => 'Previous error',
    ]);

    (new ExtractSermonClipVerticalVideo($clip))->handle();

    $clip->refresh();
    expect($clip->clip_video_status)->toBe(JobStatus::Completed);
    expect($clip->clip_video_error)->toBeNull();
});

test('job is queued on the video-processing queue', function () {
    $video = createVideoWithVerticalAndTranscript();

    $clip = SermonClip::factory()->create([
        'sermon_video_id' => $video->id,
        'start_segment_index' => 0,
        'end_segment_index' => 3,
    ]);

    $job = new ExtractSermonClipVerticalVideo($clip);
    expect($job->queue)->toBe('video-processing');
});
