<?php

use App\Enums\JobStatus;
use App\Jobs\ExtractSermonClipVerticalVideo;
use App\Models\SermonClip;
use App\Models\SermonVideo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
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

    // Create a valid clip first, then move indices out of bounds directly in
    // the DB to bypass the model's saving hook validation.
    $clip = SermonClip::factory()->create([
        'sermon_video_id' => $video->id,
        'start_segment_index' => 2,
        'end_segment_index' => 4,
    ]);

    DB::table('sermon_clips')->where('id', $clip->id)->update(['end_segment_index' => 10]);
    $clip->refresh();

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

// --- Timestamp Tests ---

test('job sets clip_video_started_at when processing begins', function () {
    Carbon::setTestNow('2026-02-24 12:00:00');

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
    expect($clip->clip_video_started_at)->not->toBeNull();
    expect($clip->clip_video_started_at->toDateTimeString())->toBe('2026-02-24 12:00:00');
});

test('job sets clip_video_completed_at on successful completion', function () {
    Carbon::setTestNow('2026-02-24 12:00:00');
    Process::fake(['*' => Process::result()]);

    $video = createVideoWithVerticalAndTranscript();
    Storage::disk('public')->put($video->vertical_video_path, 'fake-vertical-content');

    $clip = SermonClip::factory()->create([
        'sermon_video_id' => $video->id,
        'start_segment_index' => 0,
        'end_segment_index' => 3,
    ]);

    (new ExtractSermonClipVerticalVideo($clip))->handle();

    $clip->refresh();
    expect($clip->clip_video_started_at)->not->toBeNull();
    expect($clip->clip_video_completed_at)->not->toBeNull();
    expect($clip->clip_video_completed_at->toDateTimeString())->toBe('2026-02-24 12:00:00');
});

test('job does not set clip_video_completed_at on failure', function () {
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
    expect($clip->clip_video_started_at)->not->toBeNull();
    expect($clip->clip_video_completed_at)->toBeNull();
});

test('job resets clip_video_completed_at on re-run', function () {
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
        'clip_video_status' => JobStatus::Completed,
        'clip_video_started_at' => now()->subMinutes(10),
        'clip_video_completed_at' => now()->subMinutes(5),
    ]);

    (new ExtractSermonClipVerticalVideo($clip))->handle();

    $clip->refresh();
    expect($clip->clip_video_completed_at)->toBeNull();
});

test('job computes clip_video_duration on successful completion', function () {
    $startTime = Carbon::parse('2026-02-24 12:00:00');
    $endTime = Carbon::parse('2026-02-24 12:02:30');

    $video = createVideoWithVerticalAndTranscript();

    $clip = SermonClip::factory()->create([
        'sermon_video_id' => $video->id,
        'start_segment_index' => 0,
        'end_segment_index' => 3,
    ]);

    $clip->update([
        'clip_video_status' => JobStatus::Completed,
        'clip_video_started_at' => $startTime,
        'clip_video_completed_at' => $endTime,
    ]);

    $clip->refresh();
    expect($clip->clip_video_duration)->toBe(150);
});

// --- Command Tests ---

test('command dispatches extraction jobs for all clips', function () {
    Queue::fake([ExtractSermonClipVerticalVideo::class]);

    $video = createVideoWithVerticalAndTranscript();

    $clip1 = SermonClip::factory()->create([
        'sermon_video_id' => $video->id,
        'start_segment_index' => 0,
        'end_segment_index' => 3,
    ]);

    $clip2 = SermonClip::factory()->create([
        'sermon_video_id' => $video->id,
        'start_segment_index' => 5,
        'end_segment_index' => 8,
    ]);

    $this->artisan('app:extract-sermon-clip-videos', ['id' => $video->id])
        ->assertSuccessful();

    Queue::assertPushed(ExtractSermonClipVerticalVideo::class, 2);
    Queue::assertPushed(ExtractSermonClipVerticalVideo::class, fn ($job) => $job->sermonClip->id === $clip1->id);
    Queue::assertPushed(ExtractSermonClipVerticalVideo::class, fn ($job) => $job->sermonClip->id === $clip2->id);
});

test('command fails for non-existent sermon video', function () {
    $this->artisan('app:extract-sermon-clip-videos', ['id' => 999])
        ->assertFailed();
});

test('command succeeds with message when video has no clips', function () {
    $video = createVideoWithVerticalAndTranscript();

    $this->artisan('app:extract-sermon-clip-videos', ['id' => $video->id])
        ->assertSuccessful();
});
