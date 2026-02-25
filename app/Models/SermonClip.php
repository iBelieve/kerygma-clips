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
        'pause_before',
        'pause_after',
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
        'pause_before' => 'float',
        'pause_after' => 'float',
        'duration' => 'float',
        'clip_video_status' => JobStatus::class,
        'clip_video_started_at' => 'immutable_datetime',
        'clip_video_completed_at' => 'immutable_datetime',
        'clip_video_duration' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (SermonClip $clip): void {
            $sermonVideo = $clip->sermonVideo;
            $segments = $sermonVideo->transcript['segments'] ?? [];

            if (! isset($segments[$clip->start_segment_index], $segments[$clip->end_segment_index])) {
                throw new \RuntimeException("Segment indices [{$clip->start_segment_index}, {$clip->end_segment_index}] are out of bounds.");
            }

            $segmentStart = (float) $segments[$clip->start_segment_index]['start'];
            $segmentEnd = (float) $segments[$clip->end_segment_index]['end'];

            // Calculate pause_before: half the gap to the preceding segment, max 0.5s
            if ($clip->start_segment_index > 0) {
                $prevEnd = (float) $segments[$clip->start_segment_index - 1]['end'];
                $gapBefore = max(0, $segmentStart - $prevEnd);
            } else {
                $gapBefore = max(0, $segmentStart);
            }
            $clip->pause_before = min($gapBefore / 2, 0.5);

            // Calculate pause_after: half the gap to the following segment, max 0.5s
            if (isset($segments[$clip->end_segment_index + 1])) {
                $nextStart = (float) $segments[$clip->end_segment_index + 1]['start'];
                $gapAfter = max(0, $nextStart - $segmentEnd);
            } else {
                $gapAfter = max(0, (float) $sermonVideo->duration - $segmentEnd);
            }
            $clip->pause_after = min($gapAfter / 2, 0.5);

            $clip->starts_at = $segmentStart - $clip->pause_before;
            $clip->ends_at = $segmentEnd + $clip->pause_after;
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
