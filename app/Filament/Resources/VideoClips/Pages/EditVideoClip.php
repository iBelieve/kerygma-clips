<?php

namespace App\Filament\Resources\VideoClips\Pages;

use App\Enums\JobStatus;
use App\Filament\Resources\VideoClips\VideoClipResource;
use App\Jobs\ExtractVideoClipVerticalVideo;
use App\Jobs\GenerateClipThumbnail;
use App\Jobs\GenerateVideoClipTitle;
use App\Models\VideoClip;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Computed;

/**
 * @extends EditRecord<VideoClip>
 *
 * @method VideoClip getRecord()
 */
class EditVideoClip extends EditRecord
{
    private const GAP_THRESHOLD_SECONDS = 2;

    protected static string $resource = VideoClipResource::class;

    protected string $view = 'filament.resources.video-clips.edit-video-clip';

    /**
     * @return array<ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('generate_title')
                    ->label('Generate Title')
                    ->icon('heroicon-o-sparkles')
                    ->action(function () {
                        GenerateVideoClipTitle::dispatch($this->getRecord());

                        Notification::make()
                            ->title('Title generation queued')
                            ->body('AI title generation has been dispatched.')
                            ->success()
                            ->send();
                    }),

                Action::make('extract_video')
                    ->label(fn () => $this->getRecord()->clip_video_status === JobStatus::Completed ? 'Re-extract Video' : 'Extract Video')
                    ->icon('heroicon-o-film')
                    ->action(function () {
                        ExtractVideoClipVerticalVideo::dispatch($this->getRecord());

                        Notification::make()
                            ->title('Clip extraction queued')
                            ->body('Clip video extraction has been dispatched.')
                            ->success()
                            ->send();
                    }),

                Action::make('generate_thumbnail')
                    ->label(fn () => $this->getRecord()->thumbnail_status === JobStatus::Completed ? 'Regenerate Thumbnail' : 'Generate Thumbnail')
                    ->icon('heroicon-o-photo')
                    ->action(function () {
                        GenerateClipThumbnail::dispatch($this->getRecord());

                        Notification::make()
                            ->title('Thumbnail generation queued')
                            ->body('Clip thumbnail generation has been dispatched.')
                            ->success()
                            ->send();
                    }),

                DeleteAction::make(),
            ])
                ->icon('heroicon-o-cog-6-tooth')
                ->label('')
                ->color('gray')
                ->button(),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->title
            ?? $this->getRecord()->video->date->timezone('America/Chicago')->format('M j, Y g:i A');
    }

    /**
     * @return list<array{type: string, timestamp?: string, text?: string, label?: string, segmentIndex?: int, words?: list<array{word: string, start?: float, end?: float, score?: float}>}>
     */
    #[Computed]
    public function transcriptRows(): array
    {
        $clip = $this->getRecord();
        $segments = $clip->video->transcript['segments'] ?? [];

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

        foreach ($clipSegments as $localIndex => $segment) {
            if ($previousEnd !== null) {
                $gap = $segment['start'] - $previousEnd;
                if ($gap > self::GAP_THRESHOLD_SECONDS) {
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
                'segmentIndex' => $clip->start_segment_index + $localIndex,
                'words' => $segment['words'] ?? [],
            ];

            $previousEnd = $segment['end'];
        }

        return $rows;
    }

    /**
     * Update the word texts for a transcript segment.
     *
     * @param  list<string>  $words  The updated word texts (one per existing word)
     */
    public function updateSegmentWords(int $segmentIndex, array $words): void
    {
        $clip = $this->getRecord();

        if ($segmentIndex < $clip->start_segment_index || $segmentIndex > $clip->end_segment_index) {
            return;
        }

        $video = $clip->video;
        $transcript = $video->transcript;
        $segment = $transcript['segments'][$segmentIndex] ?? null;

        if ($segment === null) {
            return;
        }

        $existingWords = $segment['words'] ?? [];

        if (count($words) !== count($existingWords)) {
            return;
        }

        // Trim and validate no empty words
        $words = array_map('trim', $words);
        foreach ($words as $word) {
            if ($word === '') {
                return;
            }
        }

        // Update each word's text
        foreach ($existingWords as $i => $existingWord) {
            $transcript['segments'][$segmentIndex]['words'][$i]['word'] = $words[$i];

            // Sync the corresponding entry in word_segments (matched by start timestamp)
            if (isset($transcript['word_segments']) && isset($existingWord['start'])) {
                foreach ($transcript['word_segments'] as $j => $ws) {
                    if (isset($ws['start']) && $ws['start'] === $existingWord['start']) {
                        $transcript['word_segments'][$j]['word'] = $words[$i];
                        break;
                    }
                }
            }
        }

        // Rebuild segment text from updated words
        $transcript['segments'][$segmentIndex]['text'] = implode(' ', $words);

        $video->update(['transcript' => $transcript]);

        unset($this->transcriptRows);

        $shouldReExport = in_array($clip->clip_video_status, [
            JobStatus::Completed,
            JobStatus::Processing,
        ], true);

        if ($shouldReExport) {
            ExtractVideoClipVerticalVideo::dispatch($clip);
        }

        Notification::make()
            ->title('Segment updated')
            ->body($shouldReExport ? 'Clip video re-export has been queued.' : null)
            ->success()
            ->send();
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
