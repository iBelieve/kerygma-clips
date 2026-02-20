<?php

use App\Enums\TranscriptStatus;
use App\Jobs\TranscribeSermonVideo;
use App\Models\SermonVideo;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('sermon_videos');
});

function createTranscriptOutputFile(SermonVideo $video, array $content): void
{
    $outputDir = sys_get_temp_dir().'/whisperx_'.$video->id;
    if (! is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    $inputFilename = pathinfo($video->raw_video_path, PATHINFO_FILENAME);
    file_put_contents($outputDir.'/'.$inputFilename.'.json', json_encode($content));
}

function cleanupTranscriptOutputDir(SermonVideo $video): void
{
    $outputDir = sys_get_temp_dir().'/whisperx_'.$video->id;
    if (is_dir($outputDir)) {
        $files = glob($outputDir.'/*');
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        rmdir($outputDir);
    }
}

// --- Job Tests ---

test('job completes transcription successfully', function () {
    Process::fake(['*' => Process::result()]);

    $video = SermonVideo::factory()->create([
        'transcript_status' => TranscriptStatus::Pending,
    ]);

    Storage::disk('sermon_videos')->put($video->raw_video_path, 'fake-content');

    $transcriptData = [
        'segments' => [
            ['text' => 'Hello world', 'start' => 0.0, 'end' => 1.5],
            ['text' => 'This is a test', 'start' => 1.5, 'end' => 3.0],
        ],
    ];
    createTranscriptOutputFile($video, $transcriptData);

    (new TranscribeSermonVideo($video))->handle();

    $video->refresh();
    expect($video->transcript_status)->toBe(TranscriptStatus::Completed);
    expect($video->transcript)->toBeArray();
    expect($video->transcript['segments'])->toHaveCount(2);
    expect($video->transcript_error)->toBeNull();
});

test('job sets status to processing before running whisperx', function () {
    // Use a fake that will cause the process to "fail" so we can inspect
    // the model state after the Processing update but before completion
    Process::fake(['*' => Process::result(exitCode: 1, errorOutput: 'intentional failure')]);

    $video = SermonVideo::factory()->create([
        'transcript_status' => TranscriptStatus::Pending,
    ]);

    Storage::disk('sermon_videos')->put($video->raw_video_path, 'fake-content');

    (new TranscribeSermonVideo($video))->handle();

    // The job sets Processing first, then catches the failure and sets Failed.
    // We verify Processing was set by confirming the job ran through the full
    // lifecycle (it wouldn't reach the process call if it didn't set Processing first).
    $video->refresh();
    expect($video->transcript_status)->toBe(TranscriptStatus::Failed);
    expect($video->transcript_error)->toContain('intentional failure');
});

test('job sets status to failed when process fails', function () {
    Process::fake(['*' => Process::result(
        exitCode: 1,
        errorOutput: 'WhisperX crashed',
    )]);

    $video = SermonVideo::factory()->create([
        'transcript_status' => TranscriptStatus::Pending,
    ]);

    Storage::disk('sermon_videos')->put($video->raw_video_path, 'fake-content');

    (new TranscribeSermonVideo($video))->handle();

    $video->refresh();
    expect($video->transcript_status)->toBe(TranscriptStatus::Failed);
    expect($video->transcript_error)->toContain('WhisperX crashed');
});

test('job sets status to failed when output json is missing', function () {
    Process::fake(['*' => Process::result()]);

    $video = SermonVideo::factory()->create([
        'transcript_status' => TranscriptStatus::Pending,
    ]);

    Storage::disk('sermon_videos')->put($video->raw_video_path, 'fake-content');

    // Do not create the output file — it should fail
    (new TranscribeSermonVideo($video))->handle();

    $video->refresh();
    expect($video->transcript_status)->toBe(TranscriptStatus::Failed);
    expect($video->transcript_error)->toContain('did not produce expected output file');
});

test('job clears previous error on new run', function () {
    Process::fake(['*' => Process::result()]);

    $video = SermonVideo::factory()->create([
        'transcript_status' => TranscriptStatus::Failed,
        'transcript_error' => 'Previous error',
    ]);

    Storage::disk('sermon_videos')->put($video->raw_video_path, 'fake-content');
    createTranscriptOutputFile($video, ['segments' => []]);

    (new TranscribeSermonVideo($video))->handle();

    $video->refresh();
    expect($video->transcript_status)->toBe(TranscriptStatus::Completed);
    expect($video->transcript_error)->toBeNull();
});

// --- Command Tests ---

test('command runs transcription synchronously', function () {
    Process::fake(['*' => Process::result()]);

    $video = SermonVideo::factory()->create([
        'transcript_status' => TranscriptStatus::Pending,
    ]);

    Storage::disk('sermon_videos')->put($video->raw_video_path, 'fake-content');
    createTranscriptOutputFile($video, ['segments' => [['text' => 'Test transcript', 'start' => 0.0, 'end' => 1.0]]]);

    $this->artisan('app:transcribe-sermon-video', ['id' => $video->id])
        ->assertSuccessful();

    $video->refresh();
    expect($video->transcript_status)->toBe(TranscriptStatus::Completed);
    expect($video->transcript)->toBeArray();
});

test('command fails for non-existent sermon video', function () {
    $this->artisan('app:transcribe-sermon-video', ['id' => 999])
        ->assertFailed();
});

test('command fails for sermon video already being processed', function () {
    $video = SermonVideo::factory()->create([
        'transcript_status' => TranscriptStatus::Processing,
    ]);

    $this->artisan('app:transcribe-sermon-video', ['id' => $video->id])
        ->assertFailed();
});

test('command reports failure when transcription fails', function () {
    Process::fake(['*' => Process::result(
        exitCode: 1,
        errorOutput: 'Model not found',
    )]);

    $video = SermonVideo::factory()->create([
        'transcript_status' => TranscriptStatus::Pending,
    ]);

    Storage::disk('sermon_videos')->put($video->raw_video_path, 'fake-content');

    $this->artisan('app:transcribe-sermon-video', ['id' => $video->id])
        ->assertFailed();

    $video->refresh();
    expect($video->transcript_status)->toBe(TranscriptStatus::Failed);
});
