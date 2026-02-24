<?php

namespace Database\Factories;

use App\Enums\JobStatus;
use App\Models\SermonVideo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SermonVideo>
 */
class SermonVideoFactory extends Factory
{
    protected $model = SermonVideo::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'raw_video_path' => $this->faker->date('Y-m-d').' '.$this->faker->time('H-i-s').'.mp4',
            'transcript_status' => JobStatus::Pending,
            'vertical_video_status' => JobStatus::Pending,
            'date' => $this->faker->dateTime(),
        ];
    }
}
