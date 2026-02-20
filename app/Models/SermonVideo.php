<?php

namespace App\Models;

use App\Enums\TranscriptStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'date' => 'immutable_datetime',
        'transcript_status' => TranscriptStatus::class,
        'transcript' => 'array',
        'duration' => 'integer',
        'transcription_started_at' => 'immutable_datetime',
        'transcription_completed_at' => 'immutable_datetime',
        'transcription_duration' => 'integer',
    ];
}
