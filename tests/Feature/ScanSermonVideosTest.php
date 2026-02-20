<?php

use App\Jobs\ScanSermonVideos;
use App\Services\VideoProbe;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('the scan command dispatches the scan job', function () {
    Queue::fake();

    $this->artisan('app:scan-sermon-videos')
        ->assertSuccessful();

    Queue::assertPushed(ScanSermonVideos::class, function (ScanSermonVideos $job) {
        return $job->verbose === true;
    });
});

test('the scan command runs synchronously with --sync flag', function () {
    Storage::fake('sermon_videos');

    $this->mock(VideoProbe::class, function ($mock) {
        $mock->shouldReceive('getDurationInSeconds')
            ->andReturn(3600);
    });

    $this->artisan('app:scan-sermon-videos --sync')
        ->assertSuccessful();
});
