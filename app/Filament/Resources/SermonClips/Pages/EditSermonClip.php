<?php

namespace App\Filament\Resources\SermonClips\Pages;

use App\Filament\Resources\SermonClips\SermonClipResource;
use App\Models\SermonClip;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Computed;

/**
 * @extends EditRecord<SermonClip>
 *
 * @method SermonClip getRecord()
 */
class EditSermonClip extends EditRecord
{
    protected static string $resource = SermonClipResource::class;

    protected string $view = 'filament.resources.sermon-clips.edit-sermon-clip';

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->title
            ?? $this->getRecord()->sermonVideo->date->timezone('America/Chicago')->format('M j, Y g:i A');
    }

    /**
     * @return list<array{type: string, timestamp?: string, text?: string, label?: string}>
     */
    #[Computed]
    public function transcriptRows(): array
    {
        $clip = $this->getRecord();
        $segments = $clip->sermonVideo->transcript['segments'] ?? [];

        $clipSegments = collect($segments)
            ->slice(
                $clip->start_segment_index,
                $clip->end_segment_index - $clip->start_segment_index + 1
            )
            ->values()
            ->all();

        if ($clipSegments === []) {
            return [];
        }

        $lastStart = $clipSegments[count($clipSegments) - 1]['start'];
        $useHours = $lastStart >= 3600;

        $rows = [];
        $previousEnd = null;

        foreach ($clipSegments as $segment) {
            if ($previousEnd !== null) {
                $gap = $segment['start'] - $previousEnd;
                if ($gap > 2) {
                    $rows[] = [
                        'type' => 'gap',
                        'label' => $this->formatGap($gap),
                    ];
                }
            }

            $rows[] = [
                'type' => 'segment',
                'timestamp' => $this->formatTimestamp($segment['start'], $useHours),
                'text' => trim($segment['text']),
            ];

            $previousEnd = $segment['end'];
        }

        return $rows;
    }

    private function formatTimestamp(float $seconds, bool $useHours): string
    {
        $total = (int) floor($seconds);
        $h = intdiv($total, 3600);
        $m = intdiv($total % 3600, 60);
        $s = $total % 60;

        if ($useHours) {
            return sprintf('%d:%02d:%02d', $h, $m, $s);
        }

        return sprintf('%02d:%02d', $m, $s);
    }

    private function formatGap(float $seconds): string
    {
        $total = (int) round($seconds);

        if ($total >= 60) {
            $m = intdiv($total, 60);
            $s = $total % 60;

            return sprintf('%dm %02ds pause', $m, $s);
        }

        return "{$total}s pause";
    }
}
