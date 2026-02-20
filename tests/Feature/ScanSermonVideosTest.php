<?php

use App\Services\VideoProbe;
use Illuminate\Support\Facades\Storage;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('the scan command runs synchronously', function () {
    Storage::fake('sermon_videos');

    $this->mock(VideoProbe::class, function ($mock) {
        $mock->shouldReceive('getDurationInSeconds')
            ->andReturn(3600);
    });

    $this->artisan('app:scan-sermon-videos')
        ->assertSuccessful();
});
