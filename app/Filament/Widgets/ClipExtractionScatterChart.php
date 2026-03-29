<?php

namespace App\Filament\Widgets;

use App\Models\VideoClip;
use Filament\Widgets\ChartWidget;

class ClipExtractionScatterChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Clip Extraction Time vs Clip Duration';

    protected ?string $maxHeight = '300px';

    protected int | string | array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'scatter';
    }

    protected function getData(): array
    {
        $clips = VideoClip::query()
            ->whereNotNull('clip_video_duration')
            ->select(['duration', 'clip_video_duration'])
            ->get();

        $data = $clips->map(fn (VideoClip $c): array => [
            'x' => round($c->duration, 1),
            'y' => $c->clip_video_duration,
        ])->values()->all();

        return [
            'datasets' => [
                [
                    'label' => 'Clips',
                    'data' => $data,
                    'backgroundColor' => 'rgba(245, 158, 11, 0.6)',
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
                    'title' => ['display' => true, 'text' => 'Clip Duration (sec)'],
                    'type' => 'linear',
                ],
                'y' => [
                    'title' => ['display' => true, 'text' => 'Extraction Time (sec)'],
                    'type' => 'linear',
                ],
            ],
        ];
    }
}
