<?php

namespace App\Models;

use App\Enums\JobStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SermonClip extends Model
{
    use HasFactory;

    protected $fillable = [
        'sermon_video_id',
        'start_segment_index',
        'end_segment_index',
        'starts_at',
        'ends_at',
        'ai_title',
        'clip_video_status',
        'clip_video_path',
        'clip_video_error',
        'clip_video_started_at',
        'clip_video_completed_at',
    ];

    protected $casts = [
        'start_segment_index' => 'integer',
        'end_segment_index' => 'integer',
        'starts_at' => 'float',
        'ends_at' => 'float',
        'duration' => 'float',
        'clip_video_status' => JobStatus::class,
        'clip_video_started_at' => 'immutable_datetime',
        'clip_video_completed_at' => 'immutable_datetime',
        'clip_video_duration' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (SermonClip $clip): void {
            $segments = $clip->sermonVideo->transcript['segments'] ?? [];

            if (! isset($segments[$clip->start_segment_index], $segments[$clip->end_segment_index])) {
                throw new \RuntimeException("Segment indices [{$clip->start_segment_index}, {$clip->end_segment_index}] are out of bounds.");
            }

            $clip->starts_at = $segments[$clip->start_segment_index]['start'];
            $clip->ends_at = $segments[$clip->end_segment_index]['end'];
        });
    }

    /**
     * @return BelongsTo<SermonVideo, $this>
     */
    public function sermonVideo(): BelongsTo
    {
        return $this->belongsTo(SermonVideo::class);
    }
}
