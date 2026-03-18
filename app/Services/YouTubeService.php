<?php

namespace App\Services;

use App\Models\Settings;
use App\Models\VideoClip;
use Carbon\CarbonImmutable;
use Google\Client as GoogleClient;
use Google\Service\YouTube;
use Google\Service\YouTube\Video;
use Google\Service\YouTube\VideoSnippet;
use Google\Service\YouTube\VideoStatus;
use Google_Http_MediaFileUpload;
use Illuminate\Support\Facades\Storage;

class YouTubeService
{
    private const PUBLISH_HOUR = 9;

    private const PUBLISH_TIMEZONE = 'America/Chicago';

    private const CATEGORY_PEOPLE_BLOGS = '22';

    private GoogleClient $client;

    public function __construct()
    {
        $this->client = new GoogleClient;
        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setRedirectUri(config('services.google.redirect_uri'));
        $this->client->addScope(YouTube::YOUTUBE_UPLOAD);
        $this->client->addScope(YouTube::YOUTUBE);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
    }

    public function getAuthUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    public function handleCallback(string $code): void
    {
        $token = $this->client->fetchAccessTokenWithAuthCode($code);

        if (! isset($token['access_token'])) {
            throw new \RuntimeException('YouTube OAuth error: '.($token['error_description'] ?? $token['error'] ?? 'Unknown error'));
        }

        $this->client->setAccessToken($token);

        $youtube = new YouTube($this->client);

        /** @var \Google\Service\YouTube\ChannelListResponse $channels */
        $channels = $youtube->channels->listChannels('snippet', ['mine' => true]);
        $channel = $channels->getItems()[0] ?? null;

        if ($channel === null) {
            throw new \RuntimeException('No YouTube channel found for this account.');
        }

        $settings = Settings::instance();
        $settings->update([
            'youtube_access_token' => json_encode($token),
            'youtube_refresh_token' => $token['refresh_token'] ?? $settings->youtube_refresh_token,
            'youtube_channel_id' => $channel->getId(),
            'youtube_channel_title' => $channel->getSnippet()->getTitle(),
        ]);
    }

    public function upload(VideoClip $clip, string $scheduledDate): string
    {
        $this->authenticate();

        $youtube = new YouTube($this->client);

        $publishAt = CarbonImmutable::parse($scheduledDate, self::PUBLISH_TIMEZONE)
            ->setTime(self::PUBLISH_HOUR, 0)
            ->utc();

        $snippet = new VideoSnippet;
        $snippet->setTitle(mb_substr($clip->title ?? 'Sermon Clip', 0, 100));
        $snippet->setDescription($clip->buildDescription($clip->excerpt)."\n\n#Shorts");
        $snippet->setCategoryId(self::CATEGORY_PEOPLE_BLOGS);

        $status = new VideoStatus;
        $status->setPrivacyStatus('private');
        $status->setPublishAt($publishAt->toIso8601String());

        $video = new Video;
        $video->setSnippet($snippet);
        $video->setStatus($status);

        $this->client->setDefer(true);

        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = $youtube->videos->insert('snippet,status', $video);

        $filePath = Storage::disk('public')->path($clip->clip_video_path);
        $fileSize = filesize($filePath);

        $upload = new Google_Http_MediaFileUpload(
            $this->client,
            $request,
            'video/mp4',
            '',
            true,
            (int) min($fileSize, 16 * 1024 * 1024),
        );
        $upload->setFileSize($fileSize);

        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new \RuntimeException("Cannot open clip video file: {$filePath}");
        }

        try {
            $response = false;
            while (! $response && ! feof($handle)) {
                $chunk = fread($handle, (int) min($fileSize, 16 * 1024 * 1024));
                $response = $upload->nextChunk($chunk);
            }
        } finally {
            fclose($handle);
            $this->client->setDefer(false);
        }

        if (! $response instanceof Video) {
            throw new \RuntimeException('YouTube upload failed: no response received.');
        }

        return $response->getId();
    }

    public function updateSchedule(string $videoId, string $scheduledDate): void
    {
        $this->authenticate();

        $youtube = new YouTube($this->client);

        $publishAt = CarbonImmutable::parse($scheduledDate, self::PUBLISH_TIMEZONE)
            ->setTime(self::PUBLISH_HOUR, 0)
            ->utc();

        /** @var \Google\Service\YouTube\VideoListResponse $response */
        $response = $youtube->videos->listVideos('status', ['id' => $videoId]);
        $video = $response->getItems()[0] ?? null;

        if ($video === null) {
            throw new \RuntimeException("YouTube video not found: {$videoId}");
        }

        $video->getStatus()->setPublishAt($publishAt->toIso8601String());

        $youtube->videos->update('status', $video);
    }

    public function delete(string $videoId): void
    {
        $this->authenticate();

        $youtube = new YouTube($this->client);

        try {
            $youtube->videos->delete($videoId);
        } catch (\Google\Service\Exception $e) {
            // 404 = already deleted, which is fine
            if ($e->getCode() !== 404) {
                throw $e;
            }
        }
    }

    private function authenticate(): void
    {
        $settings = Settings::instance();

        if (! $settings->hasYouTubeConnection()) {
            throw new \RuntimeException('YouTube is not connected. Please connect your YouTube channel in Settings.');
        }

        $token = json_decode($settings->youtube_access_token ?? '{}', true);

        if (is_array($token) && isset($token['access_token'])) {
            $this->client->setAccessToken($token);
        }

        // Always set the refresh token for automatic renewal
        if ($settings->youtube_refresh_token) {
            $this->client->refreshToken($settings->youtube_refresh_token);

            // Persist the new access token
            $newToken = $this->client->getAccessToken();
            if ($newToken) {
                $settings->update(['youtube_access_token' => json_encode($newToken)]);
            }
        }
    }
}
