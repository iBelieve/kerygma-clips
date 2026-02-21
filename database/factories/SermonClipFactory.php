<?php

namespace Database\Factories;

use App\Models\SermonClip;
use App\Models\SermonVideo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SermonClip>
 */
class SermonClipFactory extends Factory
{
    protected $model = SermonClip::class;

    public function definition(): array
    {
        return [
            'sermon_video_id' => SermonVideo::factory(),
            'start_segment_index' => 0,
            'end_segment_index' => 5,
        ];
    }
}
