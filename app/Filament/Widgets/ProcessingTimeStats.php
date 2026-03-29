<?php

namespace App\Filament\Widgets;

use App\Models\Video;
use App\Models\VideoClip;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class ProcessingTimeStats extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            $this->buildStat(
                'Avg Transcription Time',
                Video::query()->whereNotNull('transcription_duration'),
                'duration',
                'transcription_duration',
            ),
            $this->buildStat(
                'Avg Vertical Video Time',
                Video::query()
                    ->whereNotNull('vertical_video_duration')
                    ->where(function (Builder $query): void {
                        $query->where('is_source_vertical', false)
                            ->orWhereNull('is_source_vertical');
                    }),
                'duration',
                'vertical_video_duration',
            ),
            $this->buildStat(
                'Avg Clip Extraction Time',
                VideoClip::query()->whereNotNull('clip_video_duration'),
                'duration',
                'clip_video_duration',
            ),
        ];
    }

    /**
     * @param  Builder<Video>|Builder<VideoClip>  $query
     */
    private function buildStat(
        string $label,
        Builder $query,
        string $durationColumn,
        string $processingColumn,
    ): Stat {
        $range = (clone $query)->toBase()
            ->selectRaw("MIN($durationColumn) as min_dur, MAX($durationColumn) as max_dur, AVG($processingColumn) as avg_proc")
            ->first();

        $avgProc = $range?->avg_proc;

        if ($avgProc === null || $range->min_dur === null) {
            return Stat::make($label, 'N/A')->chart(array_fill(0, 10, 0));
        }

        $avgProcessing = (float) $avgProc;
        $min = (float) $range->min_dur;
        $max = (float) $range->max_dur;
        $bucketWidth = ($max - $min) / 10;

        if ($bucketWidth <= 0) {
            return Stat::make($label, $this->formatSeconds($avgProcessing))
                ->chart(array_fill(0, 10, (int) round($avgProcessing)));
        }

        $bucketExpr = "MIN(CAST(($durationColumn - $min) / $bucketWidth AS INTEGER), 9)";

        $buckets = (clone $query)->toBase()
            ->selectRaw("$bucketExpr as bucket, AVG($processingColumn) as avg_time")
            ->groupByRaw('bucket')
            ->orderBy('bucket')
            ->pluck('avg_time', 'bucket');

        $chartData = [];
        for ($i = 0; $i < 10; $i++) {
            $chartData[] = (int) round((float) ($buckets->get($i, 0)));
        }

        return Stat::make($label, $this->formatSeconds($avgProcessing))
            ->chart($chartData);
    }

    private function formatSeconds(float $seconds): string
    {
        $minutes = (int) floor($seconds / 60);
        $secs = (int) round($seconds % 60);

        return $minutes > 0 ? "{$minutes}m {$secs}s" : "{$secs}s";
    }
}
