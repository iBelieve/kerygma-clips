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
     * @return list<array{start: float, end: float, text: string}>
     */
    #[Computed]
    public function clipSegments(): array
    {
        $clip = $this->getRecord();
        $segments = $clip->sermonVideo->transcript['segments'] ?? [];

        return collect($segments)
            ->slice(
                $clip->start_segment_index,
                $clip->end_segment_index - $clip->start_segment_index + 1
            )
            ->map(fn (array $s): array => [
                'start' => $s['start'],
                'end' => $s['end'],
                'text' => trim($s['text']),
            ])
            ->values()
            ->all();
    }
}
