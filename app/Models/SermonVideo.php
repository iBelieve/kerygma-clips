<?php

namespace App\Models;

use App\Enums\JobStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * To understand what the `transcript` field looks like, see `docs/sample_transcript.jsonc`.
 */
class SermonVideo extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'raw_video_path',
        'transcript_status',
        'transcript',
        'transcript_error',
        'transcription_started_at',
        'transcription_completed_at',
        'duration',
        'date',
        'vertical_video_status',
        'vertical_video_path',
        'vertical_video_error',
        'vertical_video_started_at',
        'vertical_video_completed_at',
        'vertical_video_crop_center',
        'preview_frame_path',
    ];

    protected static function booted(): void
    {
        static::deleting(function (SermonVideo $video): void {
            // Delete clips individually so their deleting events fire for file cleanup
            $video->sermonClips->each->delete();

            if ($video->vertical_video_path) {
                Storage::disk('public')->delete($video->vertical_video_path);
            }

            if ($video->preview_frame_path) {
                Storage::disk('public')->delete($video->preview_frame_path);
            }
        });
    }

    /**
     * @return HasMany<SermonClip, $this>
     */
    public function sermonClips(): HasMany
    {
        return $this->hasMany(SermonClip::class);
    }

    protected $casts = [
        'date' => 'immutable_datetime',
        'transcript_status' => JobStatus::class,
        'transcript' => 'array',
        'duration' => 'integer',
        'transcription_started_at' => 'immutable_datetime',
        'transcription_completed_at' => 'immutable_datetime',
        'transcription_duration' => 'integer',
        'vertical_video_status' => JobStatus::class,
        'vertical_video_crop_center' => 'integer',
        'vertical_video_started_at' => 'immutable_datetime',
        'vertical_video_completed_at' => 'immutable_datetime',
        'vertical_video_duration' => 'integer',
    ];
}
