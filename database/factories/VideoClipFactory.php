<?php

namespace Database\Factories;

use App\Models\Video;
use App\Models\VideoClip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VideoClip>
 */
class VideoClipFactory extends Factory
{
    protected $model = VideoClip::class;

    public function definition(): array
    {
        return [
            'video_id' => Video::factory(),
            'start_segment_index' => 0,
            'end_segment_index' => 5,
        ];
    }
}
