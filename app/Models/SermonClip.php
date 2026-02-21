<?php

namespace App\Models;

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
    ];

    protected $casts = [
        'start_segment_index' => 'integer',
        'end_segment_index' => 'integer',
    ];

    /**
     * @return BelongsTo<SermonVideo, $this>
     */
    public function sermonVideo(): BelongsTo
    {
        return $this->belongsTo(SermonVideo::class);
    }
}
