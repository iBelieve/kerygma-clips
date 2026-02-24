<?php

use App\Enums\JobStatus;
use App\Jobs\ConvertToVerticalVideo;
use App\Jobs\ScanSermonVideos;
use App\Jobs\TranscribeSermonVideo;
use App\Models\SermonVideo;
use App\Services\VideoProbe;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('sermon_videos');
    Queue::fake([TranscribeSermonVideo::class, ConvertToVerticalVideo::class]);

    $this->mock(VideoProbe::class, function ($mock) {
        $mock->shouldReceive('getDurationInSeconds')
            ->andReturn(3600);
    });
});

test('it creates a sermon video entry for a valid video file', function () {
    createOldVideoFile('2025-12-10 18-53-50.m4v');

    ScanSermonVideos::dispatchSync();

    expect(SermonVideo::count())->toBe(1);

    $video = SermonVideo::first();
    expect($video->raw_video_path)->toBe('2025-12-10 18-53-50.m4v');
    expect($video->title)->toBeNull();
    expect($video->transcript_status)->toBe(JobStatus::Pending);
    expect($video->date->format('Y-m-d H:i:s'))->toBe('2025-12-11 00:53:50');
    expect($video->duration)->toBe(3600);
});

test('it skips files that are too recently modified', function () {
    Storage::disk('sermon_videos')->put('2025-12-10 18-53-50.mp4', 'fake-content');

    ScanSermonVideos::dispatchSync();

    expect(SermonVideo::count())->toBe(0);
});

test('it skips files that already have a sermon video entry', function () {
    createOldVideoFile('2025-12-10 18-53-50.mp4');

    SermonVideo::create([
        'raw_video_path' => '2025-12-10 18-53-50.mp4',
        'date' => now(),
    ]);

    ScanSermonVideos::dispatchSync();

    expect(SermonVideo::count())->toBe(1);
});

test('it skips non-video files', function () {
    createOldVideoFile('2025-12-10 18-53-50.txt');
    createOldVideoFile('2025-12-10 18-53-50.jpg');

    ScanSermonVideos::dispatchSync();

    expect(SermonVideo::count())->toBe(0);
});

test('it skips video files with non-date filenames', function () {
    createOldVideoFile('random-sermon-title.mp4');

    ScanSermonVideos::dispatchSync();

    expect(SermonVideo::count())->toBe(0);
});

test('it processes multiple video files in a single run', function () {
    createOldVideoFile('2025-12-10 18-53-50.mp4');
    createOldVideoFile('2025-12-11 09-30-00.mov');
    createOldVideoFile('2025-12-12 14-00-00.mkv');

    ScanSermonVideos::dispatchSync();

    expect(SermonVideo::count())->toBe(3);
});

test('it creates sermon video with null duration when ffprobe fails', function () {
    $this->mock(VideoProbe::class, function ($mock) {
        $mock->shouldReceive('getDurationInSeconds')
            ->andReturn(null);
    });

    createOldVideoFile('2025-12-10 18-53-50.mp4');

    ScanSermonVideos::dispatchSync();

    $video = SermonVideo::first();
    expect($video)->not->toBeNull();
    expect($video->duration)->toBeNull();
});

test('it dispatches transcription job for new sermon video', function () {
    createOldVideoFile('2025-12-10 18-53-50.mp4');

    ScanSermonVideos::dispatchSync();

    Queue::assertPushed(TranscribeSermonVideo::class, function ($job) {
        return $job->sermonVideo->raw_video_path === '2025-12-10 18-53-50.mp4';
    });
});

test('it does not dispatch transcription job when transcribe is false', function () {
    createOldVideoFile('2025-12-10 18-53-50.mp4');

    ScanSermonVideos::dispatchSync(transcribe: false);

    expect(SermonVideo::count())->toBe(1);
    Queue::assertNotPushed(TranscribeSermonVideo::class);
});

test('it dispatches vertical video conversion job for new sermon video', function () {
    createOldVideoFile('2025-12-10 18-53-50.mp4');

    ScanSermonVideos::dispatchSync();

    Queue::assertPushed(ConvertToVerticalVideo::class, function ($job) {
        return $job->sermonVideo->raw_video_path === '2025-12-10 18-53-50.mp4';
    });
});

test('it does not dispatch vertical video conversion job when convertToVertical is false', function () {
    createOldVideoFile('2025-12-10 18-53-50.mp4');

    ScanSermonVideos::dispatchSync(convertToVertical: false);

    expect(SermonVideo::count())->toBe(1);
    Queue::assertNotPushed(ConvertToVerticalVideo::class);
});

test('it dispatches transcription for existing pending sermon videos', function () {
    createOldVideoFile('already-imported.mp4');
    createOldVideoFile('already-transcribed.mp4');

    $pending = SermonVideo::factory()->create([
        'raw_video_path' => 'already-imported.mp4',
        'transcript_status' => JobStatus::Pending,
    ]);

    $completed = SermonVideo::factory()->create([
        'raw_video_path' => 'already-transcribed.mp4',
        'transcript_status' => JobStatus::Completed,
    ]);

    ScanSermonVideos::dispatchSync();

    Queue::assertPushed(TranscribeSermonVideo::class, function ($job) use ($pending) {
        return $job->sermonVideo->id === $pending->id;
    });

    Queue::assertNotPushed(TranscribeSermonVideo::class, function ($job) use ($completed) {
        return $job->sermonVideo->id === $completed->id;
    });
});

test('it does not dispatch transcription for pending videos when transcribe is false', function () {
    createOldVideoFile('already-imported.mp4');

    SermonVideo::factory()->create([
        'raw_video_path' => 'already-imported.mp4',
        'transcript_status' => JobStatus::Pending,
    ]);

    ScanSermonVideos::dispatchSync(transcribe: false);

    Queue::assertNotPushed(TranscribeSermonVideo::class);
});

test('it handles empty disk with no video files', function () {
    ScanSermonVideos::dispatchSync();

    expect(SermonVideo::count())->toBe(0);
});
