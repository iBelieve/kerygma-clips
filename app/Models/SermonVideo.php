<?php

namespace App\Models;

use App\Enums\TranscriptStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read ?int $transcription_duration
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
    ];

    protected $casts = [
        'date' => 'datetime',
        'transcript_status' => TranscriptStatus::class,
        'transcript' => 'array',
        'duration' => 'integer',
        'transcription_started_at' => 'datetime',
        'transcription_completed_at' => 'datetime',
    ];

    protected function transcriptionDuration(): Attribute
    {
        return Attribute::get(function (): ?int {
            if ($this->transcription_started_at === null || $this->transcription_completed_at === null) {
                return null;
            }

            return (int) $this->transcription_started_at->diffInSeconds($this->transcription_completed_at);
        });
    }
}
