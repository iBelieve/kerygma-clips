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
        'duration',
        'date',
    ];

    protected $casts = [
        'date' => 'datetime',
        'transcript_status' => TranscriptStatus::class,
        'transcript' => 'array',
        'duration' => 'integer',
    ];
}
