<?php

namespace Database\Factories;

use App\Enums\JobStatus;
use App\Enums\VideoType;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Video>
 */
class VideoFactory extends Factory
{
    protected $model = Video::class;

    public function definition(): array
    {
        return [
            'type' => VideoType::Sermon,
            'title' => $this->faker->sentence(3),
            'raw_video_path' => $this->faker->date('Y-m-d').' '.$this->faker->time('H-i-s').'.mp4',
            'transcript_status' => JobStatus::Pending,
            'vertical_video_status' => JobStatus::Pending,
            'date' => $this->faker->dateTime(),
        ];
    }
}
