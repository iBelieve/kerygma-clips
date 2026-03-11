<?php

namespace App\Filament\Widgets;

use App\Models\VideoClip;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ClipsPerWeekChart extends ChartWidget
{
    protected ?string $heading = 'Clips Created';

    protected ?string $description = 'Weekly clip creation over the last 12 weeks';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $weeks = 12;
        $now = CarbonImmutable::now();
        $startOfCurrentWeek = $now->startOfWeek();
        $start = $startOfCurrentWeek->subWeeks($weeks - 1);

        $counts = VideoClip::query()
            ->where('created_at', '>=', $start)
            ->select(DB::raw("strftime('%Y-%W', created_at) as week"), DB::raw('count(*) as count'))
            ->groupBy('week')
            ->orderBy('week')
            ->pluck('count', 'week');

        $labels = [];
        $data = [];

        for ($i = 0; $i < $weeks; $i++) {
            $weekStart = $start->addWeeks($i);
            $key = $weekStart->format('Y-W');
            $labels[] = $weekStart->format('M j');
            $data[] = $counts->get($key, 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Clips created',
                    'data' => $data,
                    'fill' => true,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'tension' => 0.3,
                    'pointBackgroundColor' => '#f59e0b',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
