<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class FacebookReelsService
{
    private const string GRAPH_BASE_URL = 'https://graph.facebook.com/v22.0';

    private const string UPLOAD_BASE_URL = 'https://rupload.facebook.com/video-upload/v22.0';

    public function __construct(
        private string $pageId,
        private string $pageAccessToken,
    ) {}

    /**
     * Step 1: Initialize the upload session.
     *
     * @throws RequestException
     */
    public function initialize(): string
    {
        $response = Http::post(self::GRAPH_BASE_URL."/{$this->pageId}/video_reels", [
            'upload_phase' => 'start',
            'access_token' => $this->pageAccessToken,
        ])->throw();

        return $response->json('video_id');
    }

    /**
     * Step 2: Upload the video binary.
     *
     * @throws RequestException
     */
    public function upload(string $videoId, string $filePath): void
    {
        Http::withHeaders([
            'Authorization' => "OAuth {$this->pageAccessToken}",
            'offset' => '0',
            'file_size' => (string) filesize($filePath),
        ])->withBody(
            file_get_contents($filePath),
            'application/octet-stream',
        )->post(self::UPLOAD_BASE_URL."/{$videoId}")->throw();
    }

    /**
     * Step 3: Publish the uploaded video as a Reel.
     *
     * @throws RequestException
     */
    public function publish(string $videoId, string $description, ?int $scheduledPublishTime = null): void
    {
        $payload = [
            'upload_phase' => 'finish',
            'video_id' => $videoId,
            'video_state' => 'PUBLISHED',
            'description' => $description,
            'access_token' => $this->pageAccessToken,
        ];

        if ($scheduledPublishTime !== null) {
            $payload['video_state'] = 'SCHEDULED';
            $payload['scheduled_publish_time'] = $scheduledPublishTime;
        }

        Http::post(self::GRAPH_BASE_URL."/{$this->pageId}/video_reels", $payload)->throw();
    }

    /**
     * Check the processing/publishing status of a Reel.
     *
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function getStatus(string $videoId): array
    {
        $response = Http::get(self::GRAPH_BASE_URL."/{$videoId}", [
            'fields' => 'status,created_time',
            'access_token' => $this->pageAccessToken,
        ])->throw();

        return $response->json();
    }
}
