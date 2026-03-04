<?php

namespace App\Support;

use Carbon\Carbon;

class DateTimeHelpers
{
    public static function roundToNearestHalfHour(Carbon $date): Carbon
    {
        $minutes = $date->minute;
        $seconds = $date->second;
        $totalMinutes = $minutes + ($seconds / 60);

        if ($totalMinutes < 15) {
            return $date->copy()->minute(0)->second(0);
        } elseif ($totalMinutes < 45) {
            return $date->copy()->minute(30)->second(0);
        } else {
            return $date->copy()->addHour()->minute(0)->second(0);
        }
    }
}
