<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Settings extends Model
{
    protected $fillable = [
        'call_to_action',
        'youtube_access_token',
        'youtube_refresh_token',
        'youtube_channel_id',
        'youtube_channel_title',
    ];

    protected $casts = [
        'youtube_access_token' => 'encrypted',
        'youtube_refresh_token' => 'encrypted',
    ];

    public static function instance(): static
    {
        /** @var static */
        return static::firstOrCreate([]);
    }

    public function hasYouTubeConnection(): bool
    {
        return $this->youtube_refresh_token !== null && $this->youtube_channel_id !== null;
    }

    public function clearYouTubeConnection(): void
    {
        $this->update([
            'youtube_access_token' => null,
            'youtube_refresh_token' => null,
            'youtube_channel_id' => null,
            'youtube_channel_title' => null,
        ]);
    }
}
