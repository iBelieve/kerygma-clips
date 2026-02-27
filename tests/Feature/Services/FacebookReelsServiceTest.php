<?php

use App\Services\FacebookReelsService;
use Illuminate\Support\Facades\Http;

test('initialize returns video id', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['video_id' => 'vid_abc123']),
    ]);

    $service = new FacebookReelsService('page_123', 'token_xyz');
    $videoId = $service->initialize();

    expect($videoId)->toBe('vid_abc123');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'page_123/video_reels')
            && $request['upload_phase'] === 'start'
            && $request['access_token'] === 'token_xyz';
    });
});

test('initialize throws on api failure', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid token']], 401),
    ]);

    $service = new FacebookReelsService('page_123', 'token_xyz');
    $service->initialize();
})->throws(Illuminate\Http\Client\RequestException::class);

test('upload sends binary with correct headers', function () {
    Http::fake([
        'rupload.facebook.com/*' => Http::response(['success' => true]),
    ]);

    $tempFile = tempnam(sys_get_temp_dir(), 'fb_test_');
    file_put_contents($tempFile, 'fake-video-content');

    try {
        $service = new FacebookReelsService('page_123', 'token_xyz');
        $service->upload('vid_abc123', $tempFile);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'rupload.facebook.com/video-upload/v22.0/vid_abc123')
                && $request->hasHeader('Authorization', 'OAuth token_xyz')
                && $request->hasHeader('offset', '0');
        });
    } finally {
        @unlink($tempFile);
    }
});

test('publish sends correct payload for immediate publish', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['success' => true]),
    ]);

    $service = new FacebookReelsService('page_123', 'token_xyz');
    $service->publish('vid_abc123', 'My awesome reel');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'page_123/video_reels')
            && $request['upload_phase'] === 'finish'
            && $request['video_id'] === 'vid_abc123'
            && $request['video_state'] === 'PUBLISHED'
            && $request['description'] === 'My awesome reel'
            && ! isset($request['scheduled_publish_time']);
    });
});

test('publish sends scheduled payload with timestamp', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['success' => true]),
    ]);

    $timestamp = 1740826800;
    $service = new FacebookReelsService('page_123', 'token_xyz');
    $service->publish('vid_abc123', 'Scheduled reel', $timestamp);

    Http::assertSent(function ($request) use ($timestamp) {
        return str_contains($request->url(), 'page_123/video_reels')
            && $request['upload_phase'] === 'finish'
            && $request['video_state'] === 'SCHEDULED'
            && $request['scheduled_publish_time'] === $timestamp;
    });
});

test('getStatus returns status array', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'status' => ['video_status' => 'ready'],
            'created_time' => '2026-02-27T12:00:00+0000',
            'id' => 'vid_abc123',
        ]),
    ]);

    $service = new FacebookReelsService('page_123', 'token_xyz');
    $status = $service->getStatus('vid_abc123');

    expect($status)->toHaveKey('status')
        ->and($status['status']['video_status'])->toBe('ready')
        ->and($status['created_time'])->toBe('2026-02-27T12:00:00+0000');

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'vid_abc123')
            && str_contains($url, 'fields=status,created_time');
    });
});
