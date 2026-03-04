<?php

namespace App\Filament\Resources\SermonClips\Pages;

use App\Enums\JobStatus;
use App\Filament\Resources\SermonClips\SermonClipResource;
use App\Jobs\ExtractSermonClipVerticalVideo;
use App\Jobs\GenerateSermonClipTitle;
use App\Jobs\PublishSermonClipToFacebook;
use App\Models\SermonClip;
use App\Services\FacebookReelsService;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
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

    /**
     * @return array<Action|ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish_to_facebook')
                ->label('Publish to Facebook')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->visible(fn (): bool => $this->getRecord()->canPublishToFacebook())
                ->schema([
                    Textarea::make('fb_reel_description')
                        ->label('Description')
                        ->default(fn (): string => $this->getRecord()->buildDescription($this->getRecord()->excerpt ?? ''))
                        ->rows(6),
                    DateTimePicker::make('fb_reel_scheduled_for')
                        ->label('Schedule for (optional)')
                        ->native(false)
                        ->minDate(now()->addMinutes(10))
                        ->timezone('America/Chicago'),
                ])
                ->action(function (array $data) {
                    $record = $this->getRecord();

                    $record->update([
                        'fb_reel_description' => $data['fb_reel_description'] ?? null,
                        'fb_reel_scheduled_for' => $data['fb_reel_scheduled_for'] ?? null,
                    ]);

                    PublishSermonClipToFacebook::dispatch($record);

                    Notification::make()
                        ->title('Facebook publishing queued')
                        ->body($data['fb_reel_scheduled_for']
                            ? 'Reel will be scheduled for the selected time.'
                            : 'Reel will be published immediately.')
                        ->success()
                        ->send();
                }),

            ActionGroup::make([
                Action::make('generate_title')
                    ->label('Generate Title')
                    ->icon('heroicon-o-sparkles')
                    ->action(function () {
                        GenerateSermonClipTitle::dispatch($this->getRecord());

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
                        ExtractSermonClipVerticalVideo::dispatch($this->getRecord());

                        Notification::make()
                            ->title('Clip extraction queued')
                            ->body('Clip video extraction has been dispatched.')
                            ->success()
                            ->send();
                    }),

                Action::make('check_reel_status')
                    ->label('Check Reel Status')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (): bool => $this->getRecord()->fb_reel_status === JobStatus::Completed && $this->getRecord()->fb_reel_id !== null)
                    ->action(function () {
                        $record = $this->getRecord();

                        try {
                            $facebook = app(FacebookReelsService::class);
                            $status = $facebook->getStatus($record->fb_reel_id);

                            if ($record->fb_reel_published_at === null && isset($status['created_time'])) {
                                $record->update([
                                    'fb_reel_published_at' => CarbonImmutable::parse($status['created_time']),
                                ]);
                            }

                            $videoStatus = $status['status']['video_status'] ?? 'unknown';

                            Notification::make()
                                ->title('Reel status: '.$videoStatus)
                                ->body($record->fb_reel_published_at
                                    ? 'Published at: '.$record->fb_reel_published_at->timezone('America/Chicago')->format('M j, Y g:i A')
                                    : 'Not yet published')
                                ->info()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Failed to check status')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
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
            ?? $this->getRecord()->sermonVideo->date->timezone('America/Chicago')->format('M j, Y g:i A');
    }

    /**
     * @return list<array{type: string, timestamp?: string, text?: string, label?: string, segmentIndex?: int, words?: list<array{word: string, start?: float, end?: float, score?: float}>}>
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

        foreach ($clipSegments as $localIndex => $segment) {
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

        $sermonVideo = $clip->sermonVideo;
        $transcript = $sermonVideo->transcript;
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

        $sermonVideo->update(['transcript' => $transcript]);

        unset($this->transcriptRows);

        Notification::make()
            ->title('Segment updated')
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
