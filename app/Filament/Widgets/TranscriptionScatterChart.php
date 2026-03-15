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
            ->select(['duration', 'transcription_duration', 'diarize'])
            ->get();

        $mapFn = fn (Video $v): array => [
            'x' => round($v->duration / 60, 1),
            'y' => round($v->transcription_duration / 60, 1),
        ];

        $withoutDiarization = $videos->filter(fn (Video $v): bool => ! $v->diarize)->map($mapFn)->values()->all();
        $withDiarization = $videos->filter(fn (Video $v): bool => (bool) $v->diarize)->map($mapFn)->values()->all();

        return [
            'datasets' => [
                [
                    'label' => 'Without diarization',
                    'data' => $withoutDiarization,
                    'backgroundColor' => 'rgba(245, 158, 11, 0.6)',
                    'pointRadius' => 4,
                ],
                [
                    'label' => 'With diarization',
                    'data' => $withDiarization,
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
                    'title' => ['display' => true, 'text' => 'Transcription Time (min)'],
                    'type' => 'linear',
                ],
            ],
        ];
    }
}
