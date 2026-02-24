<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
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
    ];

    protected $casts = [
        'start_segment_index' => 'integer',
        'end_segment_index' => 'integer',
        'starts_at' => 'float',
        'ends_at' => 'float',
    ];

    protected static function booted(): void
    {
        static::saving(function (SermonClip $clip): void {
            $segments = $clip->sermonVideo->transcript['segments'] ?? [];

            if (isset($segments[$clip->start_segment_index], $segments[$clip->end_segment_index])) {
                $clip->starts_at = $segments[$clip->start_segment_index]['start'];
                $clip->ends_at = $segments[$clip->end_segment_index]['end'];
            }
        });
    }

    /**
     * Duration of the clip in seconds, computed from starts_at and ends_at.
     *
     * @return Attribute<float|null, never>
     */
    protected function duration(): Attribute
    {
        return Attribute::make(
            get: fn (): ?float => $this->starts_at !== null && $this->ends_at !== null
                ? $this->ends_at - $this->starts_at
                : null,
        );
    }

    /**
     * @return BelongsTo<SermonVideo, $this>
     */
    public function sermonVideo(): BelongsTo
    {
        return $this->belongsTo(SermonVideo::class);
    }
}
