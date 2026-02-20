<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SermonVideo extends Model
{
    protected $fillable = [
        'title',
        'raw_video_path',
        'transcript_status',
        'duration',
        'date',
    ];

    protected $casts = [
        'date' => 'datetime',
        'duration' => 'integer',
    ];
}
