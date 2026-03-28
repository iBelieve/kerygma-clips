<?php

namespace App\Filament\Widgets;

use App\Enums\JobStatus;
use App\Models\Video;
use App\Models\VideoClip;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class ProcessingTimeChart extends ChartWidget
{
    protected ?string $heading = 'Processing Time vs Content Duration';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '400px';

    public ?string $filter = '3_months';

    protected function getFilters(): ?array
    {
        return [
            '3_months' => 'Last 3 months',
            '6_months' => 'Last 6 months',
            'all' => 'All time',
        ];
    }

    protected function getType(): string
    {
        return 'scatter';
    }

    protected function getData(): array
    {
        $since = match ($this->filter) {
            '3_months' => CarbonImmutable::now()->subMonths(3),
            '6_months' => CarbonImmutable::now()->subMonths(6),
            default => null,
        };

        $transcriptionData = $this->getVideoScatterData(
            'transcript_status',
            'transcription_duration',
            'transcription_completed_at',
            $since,
        );

        $verticalVideoData = $this->getVideoScatterData(
            'vertical_video_status',
            'vertical_video_duration',
            'vertical_video_completed_at',
            $since,
        );

        $clipData = $this->getClipScatterData($since);

        return [
            'datasets' => [
                [
                    'label' => 'Transcription',
                    'data' => $transcriptionData,
                    'backgroundColor' => 'rgba(245, 158, 11, 0.6)',
                    'borderColor' => 'rgb(245, 158, 11)',
                    'pointRadius' => 5,
                ],
                [
                    'label' => 'Vertical Video',
                    'data' => $verticalVideoData,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.6)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'pointRadius' => 5,
                ],
                [
                    'label' => 'Clip Extraction',
                    'data' => $clipData,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.6)',
                    'borderColor' => 'rgb(16, 185, 129)',
                    'pointRadius' => 5,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Content Duration (minutes)',
                    ],
                    'beginAtZero' => true,
                ],
                'y' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Processing Time (minutes)',
                    ],
                    'beginAtZero' => true,
                ],
            ],
        ];
    }

    /**
     * @return list<array{x: float, y: float}>
     */
    private function getVideoScatterData(
        string $statusColumn,
        string $processingDurationColumn,
        string $completedAtColumn,
        ?CarbonImmutable $since,
    ): array {
        $query = Video::query()
            ->where($statusColumn, JobStatus::Completed)
            ->whereNotNull($processingDurationColumn)
            ->where('duration', '>', 0);

        if ($since) {
            $query->where($completedAtColumn, '>=', $since);
        }

        return $query
            ->select('duration', $processingDurationColumn)
            ->get()
            ->map(fn (Video $video) => [
                'x' => round($video->duration / 60, 1),
                'y' => round($video->{$processingDurationColumn} / 60, 1),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{x: float, y: float}>
     */
    private function getClipScatterData(?CarbonImmutable $since): array
    {
        $query = VideoClip::query()
            ->where('clip_video_status', JobStatus::Completed)
            ->whereNotNull('clip_video_duration')
            ->where('duration', '>', 0);

        if ($since) {
            $query->where('clip_video_completed_at', '>=', $since);
        }

        return $query
            ->select('duration', 'clip_video_duration')
            ->get()
            ->map(fn (VideoClip $clip) => [
                'x' => round($clip->duration / 60, 1),
                'y' => round($clip->clip_video_duration / 60, 1),
            ])
            ->values()
            ->all();
    }
}
