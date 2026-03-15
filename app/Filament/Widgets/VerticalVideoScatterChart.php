<?php

namespace App\Filament\Widgets;

use App\Models\Video;
use Filament\Widgets\ChartWidget;

class VerticalVideoScatterChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Vertical Video Time vs Video Duration';

    protected ?string $maxHeight = '300px';

    protected int | string | array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'scatter';
    }

    protected function getData(): array
    {
        $videos = Video::query()
            ->whereNotNull('vertical_video_duration')
            ->whereNotNull('duration')
            ->where('duration', '>', 0)
            ->select(['duration', 'vertical_video_duration', 'is_source_vertical'])
            ->get();

        $mapFn = fn (Video $v): array => [
            'x' => round($v->duration / 60, 1),
            'y' => round($v->vertical_video_duration / 60, 1),
        ];

        $nonVertical = $videos->filter(fn (Video $v): bool => ! $v->is_source_vertical)->map($mapFn)->values()->all();
        $vertical = $videos->filter(fn (Video $v): bool => (bool) $v->is_source_vertical)->map($mapFn)->values()->all();

        return [
            'datasets' => [
                [
                    'label' => 'Horizontal source',
                    'data' => $nonVertical,
                    'backgroundColor' => 'rgba(245, 158, 11, 0.6)',
                    'pointRadius' => 4,
                ],
                [
                    'label' => 'Already vertical',
                    'data' => $vertical,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.6)',
                    'pointRadius' => 4,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => [
                    'title' => ['display' => true, 'text' => 'Video Duration (min)'],
                    'type' => 'linear',
                ],
                'y' => [
                    'title' => ['display' => true, 'text' => 'Vertical Video Time (min)'],
                    'type' => 'linear',
                ],
            ],
        ];
    }
}
