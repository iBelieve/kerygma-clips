<?php

use App\Support\DateTimeHelpers;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $videos = DB::table('sermon_videos')->select('id', 'date')->get();

        foreach ($videos as $video) {
            $date = CarbonImmutable::parse($video->date);
            $rounded = DateTimeHelpers::roundToNearestHalfHour($date);

            DB::table('sermon_videos')
                ->where('id', $video->id)
                ->update(['date' => $rounded]);
        }
    }
};
