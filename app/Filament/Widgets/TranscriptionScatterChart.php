<?php

namespace App\Filament\Widgets;

use App\Models\Video;
use Filament\Widgets\ChartWidget;

class TranscriptionScatterChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Transcription Time vs Video Duration';

    protected ?string $maxHeight = '300px';

    protected int | string | array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'scatter';
    }

    protected function getData(): array
    {
        $videos = Video::query()
            ->whereNotNull('transcription_duration')
            ->whereNotNull('duration')
            ->where('duration', '>', 0)
            ->select(['duration', 'transcription_duration'])
            ->get();

        $data = $videos->map(fn (Video $v) => [
            'x' => round($v->duration / 60, 1),
            'y' => round($v->transcription_duration / 60, 1),
        ])->values()->all();

        return [
            'datasets' => [
                [
                    'label' => 'Videos',
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
                    'title' => ['display' => true, 'text' => 'Video Duration (min)'],
                    'type' => 'linear',
                ],
                'y' => [
                    'title' => ['display' => true, 'text' => 'Transcription Time (min)'],
                    'type' => 'linear',
                ],
            ],
        ];
    }
}
