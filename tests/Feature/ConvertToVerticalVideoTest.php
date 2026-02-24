<?php

use App\Enums\JobStatus;
use App\Jobs\ConvertToVerticalVideo;
use App\Models\SermonVideo;
use App\Services\VideoProbe;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('sermon_videos');
    Storage::fake('public');
});

// --- Job Tests ---

test('job completes vertical conversion successfully', function () {
    Process::fake(['*' => Process::result()]);

    $this->mock(VideoProbe::class, function ($mock) {
        $mock->shouldReceive('getVideoDimensions')
            ->andReturn(['width' => 1920, 'height' => 1080]);
    });

    $video = SermonVideo::factory()->create([
        'vertical_video_status' => JobStatus::Pending,
    ]);

    Storage::disk('sermon_videos')->put($video->raw_video_path, 'fake-content');

    (new ConvertToVerticalVideo($video))->handle(app(VideoProbe::class));

    $video->refresh();
    expect($video->vertical_video_status)->toBe(JobStatus::Completed);
    expect($video->vertical_video_path)->toBe('vertical/'.pathinfo($video->raw_video_path, PATHINFO_FILENAME).'.mp4');
    expect($video->vertical_video_error)->toBeNull();
});

test('job sets status to failed when ffmpeg fails', function () {
    Process::fake(['*' => Process::result(
        exitCode: 1,
        errorOutput: 'ffmpeg crashed',
    )]);

    $this->mock(VideoProbe::class, function ($mock) {
        $mock->shouldReceive('getVideoDimensions')
            ->andReturn(['width' => 1920, 'height' => 1080]);
    });

    $video = SermonVideo::factory()->create([
        'vertical_video_status' => JobStatus::Pending,
    ]);

    Storage::disk('sermon_videos')->put($video->raw_video_path, 'fake-content');

    (new ConvertToVerticalVideo($video))->handle(app(VideoProbe::class));

    $video->refresh();
    expect($video->vertical_video_status)->toBe(JobStatus::Failed);
    expect($video->vertical_video_error)->toContain('ffmpeg crashed');
});

test('job sets status to failed when video dimensions cannot be detected', function () {
    $this->mock(VideoProbe::class, function ($mock) {
        $mock->shouldReceive('getVideoDimensions')
            ->andReturn(null);
    });

    $video = SermonVideo::factory()->create([
        'vertical_video_status' => JobStatus::Pending,
    ]);

    Storage::disk('sermon_videos')->put($video->raw_video_path, 'fake-content');

    (new ConvertToVerticalVideo($video))->handle(app(VideoProbe::class));

    $video->refresh();
    expect($video->vertical_video_status)->toBe(JobStatus::Failed);
    expect($video->vertical_video_error)->toContain('Failed to detect video dimensions');
});

test('job sets status to timed_out when process times out', function () {
    $process = new \Symfony\Component\Process\Process(['ffmpeg']);
    $process->setTimeout(7200);

    Process::fake(fn () => throw new \Symfony\Component\Process\Exception\ProcessTimedOutException(
        $process,
        \Symfony\Component\Process\Exception\ProcessTimedOutException::TYPE_GENERAL
    ));

    $this->mock(VideoProbe::class, function ($mock) {
        $mock->shouldReceive('getVideoDimensions')
            ->andReturn(['width' => 1920, 'height' => 1080]);
    });

    $video = SermonVideo::factory()->create([
        'vertical_video_status' => JobStatus::Pending,
    ]);

    Storage::disk('sermon_videos')->put($video->raw_video_path, 'fake-content');

    (new ConvertToVerticalVideo($video))->handle(app(VideoProbe::class));

    $video->refresh();
    expect($video->vertical_video_status)->toBe(JobStatus::TimedOut);
    expect($video->vertical_video_error)->toContain('exceeded the timeout');
});

test('job clears previous error on new run', function () {
    Process::fake(['*' => Process::result()]);

    $this->mock(VideoProbe::class, function ($mock) {
        $mock->shouldReceive('getVideoDimensions')
            ->andReturn(['width' => 1920, 'height' => 1080]);
    });

    $video = SermonVideo::factory()->create([
        'vertical_video_status' => JobStatus::Failed,
        'vertical_video_error' => 'Previous error',
    ]);

    Storage::disk('sermon_videos')->put($video->raw_video_path, 'fake-content');

    (new ConvertToVerticalVideo($video))->handle(app(VideoProbe::class));

    $video->refresh();
    expect($video->vertical_video_status)->toBe(JobStatus::Completed);
    expect($video->vertical_video_error)->toBeNull();
});

test('job passes correct crop parameters to ffmpeg for centered video', function () {
    Process::fake(['*' => Process::result()]);

    $this->mock(VideoProbe::class, function ($mock) {
        $mock->shouldReceive('getVideoDimensions')
            ->andReturn(['width' => 1920, 'height' => 1080]);
    });

    $video = SermonVideo::factory()->create([
        'vertical_video_status' => JobStatus::Pending,
        'vertical_video_crop_center' => 50,
    ]);

    Storage::disk('sermon_videos')->put($video->raw_video_path, 'fake-content');

    (new ConvertToVerticalVideo($video))->handle(app(VideoProbe::class));

    // crop_w = round(1080 * 9/16) = 608
    // center_x = round(1920 * 50/100) = 960
    // crop_x = 960 - 304 = 656
    Process::assertRan(function ($process) {
        $command = $process->command;
        $vfIndex = array_search('-vf', $command);

        return $vfIndex !== false && str_contains($command[$vfIndex + 1], 'crop=608:1080:656:0');
    });
});

test('job clamps crop position to stay within frame bounds', function () {
    Process::fake(['*' => Process::result()]);

    $this->mock(VideoProbe::class, function ($mock) {
        $mock->shouldReceive('getVideoDimensions')
            ->andReturn(['width' => 1920, 'height' => 1080]);
    });

    // Set crop center to far right (100%)
    $video = SermonVideo::factory()->create([
        'vertical_video_status' => JobStatus::Pending,
        'vertical_video_crop_center' => 100,
    ]);

    Storage::disk('sermon_videos')->put($video->raw_video_path, 'fake-content');

    (new ConvertToVerticalVideo($video))->handle(app(VideoProbe::class));

    // crop_w = 608, source_width = 1920
    // center_x = round(1920 * 100/100) = 1920
    // crop_x = min(1920 - 304, 1920 - 608) = min(1616, 1312) = 1312
    Process::assertRan(function ($process) {
        $command = $process->command;
        $vfIndex = array_search('-vf', $command);

        return $vfIndex !== false && str_contains($command[$vfIndex + 1], 'crop=608:1080:1312:0');
    });
});

// --- Timestamp Tests ---

test('job sets vertical_video_started_at when processing begins', function () {
    Carbon::setTestNow('2026-02-24 12:00:00');

    $this->mock(VideoProbe::class, function ($mock) {
        $mock->shouldReceive('getVideoDimensions')
            ->andReturn(null);
    });

    $video = SermonVideo::factory()->create([
        'vertical_video_status' => JobStatus::Pending,
    ]);

    Storage::disk('sermon_videos')->put($video->raw_video_path, 'fake-content');

    (new ConvertToVerticalVideo($video))->handle(app(VideoProbe::class));

    $video->refresh();
    expect($video->vertical_video_started_at)->not->toBeNull();
    expect($video->vertical_video_started_at->toDateTimeString())->toBe('2026-02-24 12:00:00');
});

test('job sets vertical_video_completed_at on successful completion', function () {
    Carbon::setTestNow('2026-02-24 12:00:00');

    Process::fake(['*' => Process::result()]);

    $this->mock(VideoProbe::class, function ($mock) {
        $mock->shouldReceive('getVideoDimensions')
            ->andReturn(['width' => 1920, 'height' => 1080]);
    });

    $video = SermonVideo::factory()->create([
        'vertical_video_status' => JobStatus::Pending,
    ]);

    Storage::disk('sermon_videos')->put($video->raw_video_path, 'fake-content');

    (new ConvertToVerticalVideo($video))->handle(app(VideoProbe::class));

    $video->refresh();
    expect($video->vertical_video_started_at)->not->toBeNull();
    expect($video->vertical_video_completed_at)->not->toBeNull();
    expect($video->vertical_video_completed_at->toDateTimeString())->toBe('2026-02-24 12:00:00');
});

test('job does not set vertical_video_completed_at on failure', function () {
    $this->mock(VideoProbe::class, function ($mock) {
        $mock->shouldReceive('getVideoDimensions')
            ->andReturn(null);
    });

    $video = SermonVideo::factory()->create([
        'vertical_video_status' => JobStatus::Pending,
    ]);

    Storage::disk('sermon_videos')->put($video->raw_video_path, 'fake-content');

    (new ConvertToVerticalVideo($video))->handle(app(VideoProbe::class));

    $video->refresh();
    expect($video->vertical_video_started_at)->not->toBeNull();
    expect($video->vertical_video_completed_at)->toBeNull();
});

test('job resets vertical_video_completed_at on re-run', function () {
    $this->mock(VideoProbe::class, function ($mock) {
        $mock->shouldReceive('getVideoDimensions')
            ->andReturn(null);
    });

    $video = SermonVideo::factory()->create([
        'vertical_video_status' => JobStatus::Completed,
        'vertical_video_started_at' => now()->subMinutes(10),
        'vertical_video_completed_at' => now()->subMinutes(5),
    ]);

    Storage::disk('sermon_videos')->put($video->raw_video_path, 'fake-content');

    (new ConvertToVerticalVideo($video))->handle(app(VideoProbe::class));

    $video->refresh();
    expect($video->vertical_video_completed_at)->toBeNull();
});

test('job computes vertical_video_duration on successful completion', function () {
    $startTime = Carbon::parse('2026-02-24 12:00:00');
    $endTime = Carbon::parse('2026-02-24 12:05:30');

    Carbon::setTestNow($startTime);

    $video = SermonVideo::factory()->create([
        'vertical_video_status' => JobStatus::Pending,
    ]);

    $video->update([
        'vertical_video_status' => JobStatus::Completed,
        'vertical_video_started_at' => $startTime,
        'vertical_video_completed_at' => $endTime,
    ]);

    $video->refresh();
    expect($video->vertical_video_duration)->toBe(330);
});

// --- Command Tests ---

test('command runs conversion synchronously', function () {
    Process::fake(['*' => Process::result()]);

    $this->mock(VideoProbe::class, function ($mock) {
        $mock->shouldReceive('getVideoDimensions')
            ->andReturn(['width' => 1920, 'height' => 1080]);
    });

    $video = SermonVideo::factory()->create([
        'vertical_video_status' => JobStatus::Pending,
    ]);

    Storage::disk('sermon_videos')->put($video->raw_video_path, 'fake-content');

    $this->artisan('app:convert-to-vertical-video', ['id' => $video->id])
        ->assertSuccessful();

    $video->refresh();
    expect($video->vertical_video_status)->toBe(JobStatus::Completed);
});

test('command fails for non-existent sermon video', function () {
    $this->artisan('app:convert-to-vertical-video', ['id' => 999])
        ->assertFailed();
});

test('command fails for sermon video already being converted', function () {
    $video = SermonVideo::factory()->create([
        'vertical_video_status' => JobStatus::Processing,
    ]);

    $this->artisan('app:convert-to-vertical-video', ['id' => $video->id])
        ->assertFailed();
});

test('command reports failure when conversion fails', function () {
    $this->mock(VideoProbe::class, function ($mock) {
        $mock->shouldReceive('getVideoDimensions')
            ->andReturn(null);
    });

    $video = SermonVideo::factory()->create([
        'vertical_video_status' => JobStatus::Pending,
    ]);

    Storage::disk('sermon_videos')->put($video->raw_video_path, 'fake-content');

    $this->artisan('app:convert-to-vertical-video', ['id' => $video->id])
        ->assertFailed();

    $video->refresh();
    expect($video->vertical_video_status)->toBe(JobStatus::Failed);
});
