<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class DateTimeHelpers
{
    public static function roundToNearestHalfHour(CarbonImmutable $date): CarbonImmutable
    {
        $minutes = $date->minute;
        $seconds = $date->second;
        $totalMinutes = $minutes + ($seconds / 60);

        if ($totalMinutes < 15) {
            return $date->minute(0)->second(0);
        } elseif ($totalMinutes < 45) {
            return $date->minute(30)->second(0);
        } else {
            return $date->addHour()->minute(0)->second(0);
        }
    }
}
