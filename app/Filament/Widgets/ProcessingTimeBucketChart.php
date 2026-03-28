<?php

namespace App\Filament\Widgets;

use App\Enums\JobStatus;
use App\Models\Video;
use App\Models\VideoClip;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;

class ProcessingTimeBucketChart extends ChartWidget
{
    protected ?string $heading = 'Avg Processing Time by Content Length';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '400px';

    /** @var list<array{label: string, min: int, max: int|null}> */
    private const BUCKETS = [
        ['label' => '0–15 min', 'min' => 0, 'max' => 900],
        ['label' => '15–30 min', 'min' => 900, 'max' => 1800],
        ['label' => '30–60 min', 'min' => 1800, 'max' => 3600],
        ['label' => '60+ min', 'min' => 3600, 'max' => null],
    ];

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $transcriptionVideos = Video::query()
            ->where('transcript_status', JobStatus::Completed)
            ->whereNotNull('transcription_duration')
            ->where('duration', '>', 0)
            ->select('duration', 'transcription_duration')
            ->get();

        $verticalVideos = Video::query()
            ->where('vertical_video_status', JobStatus::Completed)
            ->whereNotNull('vertical_video_duration')
            ->where('duration', '>', 0)
            ->select('duration', 'vertical_video_duration')
            ->get();

        $clips = VideoClip::query()
            ->where('clip_video_status', JobStatus::Completed)
            ->whereNotNull('clip_video_duration')
            ->where('duration', '>', 0)
            ->select('duration', 'clip_video_duration')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Transcription',
                    'data' => $this->bucketAverages($transcriptionVideos, 'duration', 'transcription_duration'),
                    'backgroundColor' => 'rgba(245, 158, 11, 0.6)',
                    'borderColor' => 'rgb(245, 158, 11)',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'Vertical Video',
                    'data' => $this->bucketAverages($verticalVideos, 'duration', 'vertical_video_duration'),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.6)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'Clip Extraction',
                    'data' => $this->bucketAverages($clips, 'duration', 'clip_video_duration'),
                    'backgroundColor' => 'rgba(16, 185, 129, 0.6)',
                    'borderColor' => 'rgb(16, 185, 129)',
                    'borderWidth' => 1,
                ],
            ],
            'labels' => array_map(fn (array $b) => $b['label'], self::BUCKETS),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Avg Processing Time (minutes)',
                    ],
                    'beginAtZero' => true,
                ],
            ],
        ];
    }

    /**
     * @param  Collection<int, Video>|Collection<int, VideoClip>  $items
     * @return list<float>
     */
    private function bucketAverages(Collection $items, string $durationCol, string $processingCol): array
    {
        return array_map(function (array $bucket) use ($items, $durationCol, $processingCol): float {
            $filtered = $items->filter(function ($item) use ($bucket, $durationCol) {
                $d = $item->{$durationCol};

                return $d >= $bucket['min'] && ($bucket['max'] === null || $d < $bucket['max']);
            });

            if ($filtered->isEmpty()) {
                return 0;
            }

            return round($filtered->avg(fn ($item) => $item->{$processingCol} / 60), 1);
        }, self::BUCKETS);
    }
}
