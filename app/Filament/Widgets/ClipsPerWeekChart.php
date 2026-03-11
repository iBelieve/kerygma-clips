<?php

namespace App\Filament\Widgets;

use App\Models\VideoClip;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ClipsPerWeekChart extends BaseWidget
{
    protected function getStats(): array
    {
        $weeks = 12;
        $now = CarbonImmutable::now();
        $startOfCurrentWeek = $now->startOfWeek(CarbonImmutable::SUNDAY);
        $start = $startOfCurrentWeek->subWeeks($weeks - 1);

        $counts = VideoClip::query()
            ->where('created_at', '>=', $start)
            ->select(DB::raw("date(created_at, 'weekday 6', '-6 days') as week_start"), DB::raw('count(*) as count'))
            ->groupBy('week_start')
            ->orderBy('week_start')
            ->pluck('count', 'week_start');

        $chartData = [];

        for ($i = 0; $i < $weeks; $i++) {
            $weekStart = $start->addWeeks($i);
            $key = $weekStart->format('Y-m-d');
            $chartData[] = $counts->get($key, 0);
        }

        $thisWeek = $chartData[$weeks - 1];
        $lastWeek = $chartData[$weeks - 2];
        $total = array_sum($chartData);

        $trend = $lastWeek > 0
            ? (int) round(($thisWeek - $lastWeek) / $lastWeek * 100)
            : ($thisWeek > 0 ? 100 : 0);

        $trendDescription = $trend >= 0
            ? ($trend.'% increase')
            : (abs($trend).'% decrease');

        $trendIcon = $trend >= 0
            ? 'heroicon-m-arrow-trending-up'
            : 'heroicon-m-arrow-trending-down';

        $trendColor = $trend >= 0 ? 'success' : 'danger';

        return [
            Stat::make('Clips this week', (string) $thisWeek)
                ->description($trendDescription.' from last week')
                ->descriptionIcon($trendIcon)
                ->color($trendColor)
                ->chart($chartData),
            Stat::make('Clips (last 3 months)', (string) $total),
            Stat::make('Total clips', (string) VideoClip::count()),
        ];
    }
}
