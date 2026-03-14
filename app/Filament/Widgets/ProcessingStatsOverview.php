<?php

namespace App\Filament\Widgets;

use App\Enums\JobStatus;
use App\Models\Video;
use App\Models\VideoClip;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ProcessingStatsOverview extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        return [
            $this->buildRatioStat(
                'Transcription Ratio',
                Video::query()
                    ->where('transcript_status', JobStatus::Completed)
                    ->whereNotNull('transcription_duration')
                    ->where('duration', '>', 0),
                'transcription_duration',
                'duration',
                'transcription_completed_at',
            ),
            $this->buildRatioStat(
                'Vertical Video Ratio',
                Video::query()
                    ->where('vertical_video_status', JobStatus::Completed)
                    ->whereNotNull('vertical_video_duration')
                    ->where('duration', '>', 0),
                'vertical_video_duration',
                'duration',
                'vertical_video_completed_at',
            ),
            $this->buildRatioStat(
                'Clip Extraction Ratio',
                VideoClip::query()
                    ->where('clip_video_status', JobStatus::Completed)
                    ->whereNotNull('clip_video_duration')
                    ->where('duration', '>', 0),
                'clip_video_duration',
                'duration',
                'clip_video_completed_at',
            ),
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Video>|\Illuminate\Database\Eloquent\Builder<VideoClip>  $query
     */
    private function buildRatioStat(
        string $label,
        \Illuminate\Database\Eloquent\Builder $query,
        string $processingColumn,
        string $durationColumn,
        string $completedAtColumn,
    ): Stat {
        $weeks = 12;
        $now = CarbonImmutable::now();
        $startOfCurrentWeek = $now->startOfWeek(CarbonImmutable::SUNDAY);
        $start = $startOfCurrentWeek->subWeeks($weeks - 1);

        $weeklyRatios = (clone $query)
            ->where($completedAtColumn, '>=', $start)
            ->selectRaw("date({$completedAtColumn}, 'weekday 6', '-6 days') as week_start, avg(CAST({$processingColumn} AS REAL) / {$durationColumn}) as avg_ratio")
            ->groupBy('week_start')
            ->orderBy('week_start')
            ->pluck('avg_ratio', 'week_start');

        $chartData = [];
        for ($i = 0; $i < $weeks; $i++) {
            $weekStart = $start->addWeeks($i);
            $key = $weekStart->format('Y-m-d');
            $chartData[] = round((float) $weeklyRatios->get($key, 0), 2);
        }

        $overallRatio = (clone $query)
            ->selectRaw("avg(CAST({$processingColumn} AS REAL) / {$durationColumn}) as avg_ratio")
            ->value('avg_ratio');

        $overallRatio = $overallRatio !== null ? round((float) $overallRatio, 2) : null;

        $thisWeek = $chartData[$weeks - 1];
        $lastWeek = $chartData[$weeks - 2];

        $trend = $lastWeek > 0
            ? (int) round(($thisWeek - $lastWeek) / $lastWeek * 100)
            : ($thisWeek > 0 ? 100 : 0);

        // For processing ratios, lower is better (faster processing)
        $trendDescription = $trend >= 0
            ? ($trend.'% increase')
            : (abs($trend).'% decrease');

        $trendIcon = $trend <= 0
            ? 'heroicon-m-arrow-trending-down'
            : 'heroicon-m-arrow-trending-up';

        // Lower ratio = faster = good (success), higher = slower = bad (danger)
        $trendColor = $trend <= 0 ? 'success' : 'danger';

        $value = $overallRatio !== null ? $overallRatio.'x realtime' : 'N/A';

        return Stat::make($label, $value)
            ->description($trendDescription.' from last week')
            ->descriptionIcon($trendIcon)
            ->color($trendColor)
            ->chart($chartData);
    }
}
