<?php

namespace App\Filament\Resources\SermonClips\Pages;

use App\Enums\JobStatus;
use App\Filament\Resources\SermonClips\SermonClipResource;
use App\Jobs\ExtractSermonClipVerticalVideo;
use App\Jobs\GenerateSermonClipTitle;
use App\Jobs\PublishSermonClipToFacebook;
use App\Models\SermonClip;
use Filament\Actions\Action;
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
     * @return array<Action|DeleteAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_title')
                ->label('Generate Title')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->requiresConfirmation()
                ->action(function () {
                    GenerateSermonClipTitle::dispatch($this->getRecord());

                    Notification::make()
                        ->title('Title generation queued')
                        ->body('AI title generation has been dispatched.')
                        ->success()
                        ->send();
                }),

            Action::make('extract_video')
                ->label('Extract Video')
                ->icon('heroicon-o-film')
                ->color('primary')
                ->visible(fn (): bool => $this->getRecord()->clip_video_status !== JobStatus::Completed)
                ->requiresConfirmation()
                ->action(function () {
                    ExtractSermonClipVerticalVideo::dispatch($this->getRecord());

                    Notification::make()
                        ->title('Clip extraction queued')
                        ->body('Clip video extraction has been dispatched.')
                        ->success()
                        ->send();
                }),

            Action::make('publish_to_facebook')
                ->label('Publish to Facebook')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->visible(fn (): bool => $this->getRecord()->canPublishToFacebook())
                ->schema([
                    Textarea::make('fb_reel_description')
                        ->label('Caption')
                        ->default(fn (): ?string => $this->getRecord()->title)
                        ->rows(3),
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

            DeleteAction::make(),
        ];
    }

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
