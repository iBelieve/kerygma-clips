<?php

use App\Enums\JobStatus;
use App\Jobs\PublishSermonClipToFacebook;
use App\Models\SermonClip;
use App\Models\SermonVideo;
use App\Services\FacebookReelsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

function createFbTestVideo(int $segmentCount = 10): SermonVideo
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
        'duration' => $segmentCount * 5,
    ]);
}

function createFbTestClip(SermonVideo $video, array $overrides = []): SermonClip
{
    $clip = SermonClip::factory()->create(array_merge([
        'sermon_video_id' => $video->id,
        'start_segment_index' => 0,
        'end_segment_index' => 3,
        'clip_video_status' => JobStatus::Completed,
        'clip_video_path' => 'clips/1.mp4',
        'title' => 'A Great Sermon Clip',
    ], $overrides));

    Storage::disk('public')->put($clip->clip_video_path, 'fake-clip-content');

    return $clip;
}

function mockFacebookService(): FacebookReelsService
{
    $mock = Mockery::mock(FacebookReelsService::class);
    $mock->shouldReceive('initialize')->andReturn('video_123');
    $mock->shouldReceive('upload');
    $mock->shouldReceive('publish');

    app()->instance(FacebookReelsService::class, $mock);

    return $mock;
}

test('job publishes clip to facebook successfully', function () {
    Carbon::setTestNow('2026-02-27 12:00:00');
    mockFacebookService();

    $video = createFbTestVideo();
    $clip = createFbTestClip($video);

    (new PublishSermonClipToFacebook($clip))->handle(app(FacebookReelsService::class));

    $clip->refresh();
    expect($clip->fb_reel_status)->toBe(JobStatus::Completed);
    expect($clip->fb_reel_id)->toBe('video_123');
    expect($clip->fb_reel_error)->toBeNull();
    expect($clip->fb_reel_completed_at)->not->toBeNull();
    expect($clip->fb_reel_published_at)->not->toBeNull();
    expect($clip->fb_reel_published_at->toDateTimeString())->toBe('2026-02-27 12:00:00');
});

test('job sets fb_reel_started_at when processing begins', function () {
    Carbon::setTestNow('2026-02-27 12:00:00');
    mockFacebookService();

    $video = createFbTestVideo();
    $clip = createFbTestClip($video);

    (new PublishSermonClipToFacebook($clip))->handle(app(FacebookReelsService::class));

    $clip->refresh();
    expect($clip->fb_reel_started_at)->not->toBeNull();
    expect($clip->fb_reel_started_at->toDateTimeString())->toBe('2026-02-27 12:00:00');
});

test('job fails when clip video is not ready', function () {
    $video = createFbTestVideo();
    $clip = SermonClip::factory()->create([
        'sermon_video_id' => $video->id,
        'start_segment_index' => 0,
        'end_segment_index' => 3,
        'clip_video_status' => JobStatus::Pending,
    ]);

    (new PublishSermonClipToFacebook($clip))->handle(app(FacebookReelsService::class));

    $clip->refresh();
    expect($clip->fb_reel_status)->toBe(JobStatus::Failed);
    expect($clip->fb_reel_error)->toContain('not ready');
});

test('job fails when clip video file is missing', function () {
    $video = createFbTestVideo();
    $clip = SermonClip::factory()->create([
        'sermon_video_id' => $video->id,
        'start_segment_index' => 0,
        'end_segment_index' => 3,
        'clip_video_status' => JobStatus::Completed,
        'clip_video_path' => 'clips/nonexistent.mp4',
    ]);

    (new PublishSermonClipToFacebook($clip))->handle(app(FacebookReelsService::class));

    $clip->refresh();
    expect($clip->fb_reel_status)->toBe(JobStatus::Failed);
    expect($clip->fb_reel_error)->toContain('not found');
});

test('job fails when facebook api errors', function () {
    $mock = Mockery::mock(FacebookReelsService::class);
    $mock->shouldReceive('initialize')->andThrow(new \RuntimeException('Facebook API error'));
    app()->instance(FacebookReelsService::class, $mock);

    $video = createFbTestVideo();
    $clip = createFbTestClip($video);

    (new PublishSermonClipToFacebook($clip))->handle(app(FacebookReelsService::class));

    $clip->refresh();
    expect($clip->fb_reel_status)->toBe(JobStatus::Failed);
    expect($clip->fb_reel_error)->toContain('Facebook API error');
});

test('job uses custom description when set', function () {
    $mock = Mockery::mock(FacebookReelsService::class);
    $mock->shouldReceive('initialize')->andReturn('video_123');
    $mock->shouldReceive('upload');
    $mock->shouldReceive('publish')
        ->withArgs(function (string $videoId, string $description) {
            return $description === 'Custom caption for the reel';
        })
        ->once();
    app()->instance(FacebookReelsService::class, $mock);

    $video = createFbTestVideo();
    $clip = createFbTestClip($video, [
        'fb_reel_description' => 'Custom caption for the reel',
    ]);

    (new PublishSermonClipToFacebook($clip))->handle(app(FacebookReelsService::class));
});

test('job falls back to title when no description set', function () {
    $mock = Mockery::mock(FacebookReelsService::class);
    $mock->shouldReceive('initialize')->andReturn('video_123');
    $mock->shouldReceive('upload');
    $mock->shouldReceive('publish')
        ->withArgs(function (string $videoId, string $description) {
            return $description === 'A Great Sermon Clip';
        })
        ->once();
    app()->instance(FacebookReelsService::class, $mock);

    $video = createFbTestVideo();
    $clip = createFbTestClip($video);

    (new PublishSermonClipToFacebook($clip))->handle(app(FacebookReelsService::class));
});

test('job passes scheduled timestamp to service', function () {
    $scheduledTime = Carbon::parse('2026-03-01 10:00:00');

    $mock = Mockery::mock(FacebookReelsService::class);
    $mock->shouldReceive('initialize')->andReturn('video_123');
    $mock->shouldReceive('upload');
    $mock->shouldReceive('publish')
        ->withArgs(function (string $videoId, string $description, ?int $scheduledPublishTime) use ($scheduledTime) {
            return $scheduledPublishTime === $scheduledTime->getTimestamp();
        })
        ->once();
    app()->instance(FacebookReelsService::class, $mock);

    $video = createFbTestVideo();
    $clip = createFbTestClip($video, [
        'fb_reel_scheduled_for' => $scheduledTime,
    ]);

    (new PublishSermonClipToFacebook($clip))->handle(app(FacebookReelsService::class));

    $clip->refresh();
    expect($clip->fb_reel_status)->toBe(JobStatus::Completed);
    expect($clip->fb_reel_published_at)->toBeNull();
});

test('job does not set fb_reel_published_at for scheduled reels', function () {
    mockFacebookService();

    $video = createFbTestVideo();
    $clip = createFbTestClip($video, [
        'fb_reel_scheduled_for' => Carbon::parse('2026-03-01 10:00:00'),
    ]);

    (new PublishSermonClipToFacebook($clip))->handle(app(FacebookReelsService::class));

    $clip->refresh();
    expect($clip->fb_reel_status)->toBe(JobStatus::Completed);
    expect($clip->fb_reel_published_at)->toBeNull();
});

test('job runs on the default queue', function () {
    $video = createFbTestVideo();
    $clip = createFbTestClip($video);

    $job = new PublishSermonClipToFacebook($clip);
    expect($job->queue)->toBeNull();
});
