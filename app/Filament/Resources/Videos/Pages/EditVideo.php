<?php

namespace App\Filament\Resources\Videos\Pages;

use App\Enums\JobStatus;
use App\Enums\VideoType;
use App\Filament\Resources\Videos\VideoResource;
use App\Jobs\ConvertToVerticalVideo;
use App\Jobs\ExtractVideoClipVerticalVideo;
use App\Jobs\GenerateVideoClipTitle;
use App\Jobs\TranscribeVideo;
use App\Models\Video;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;

/**
 * @extends EditRecord<Video>
 *
 * @method Video getRecord()
 */
class EditVideo extends EditRecord
{
    protected static string $resource = VideoResource::class;

    protected string $view = 'filament.resources.videos.edit-video';

    /** @var array<string, string> */
    public array $speakerNames = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->speakerNames = $this->getRecord()->speaker_names ?? [];
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->title
            ?? $this->getRecord()->date->timezone('America/Chicago')->format('M j, Y g:i A');
    }

    /**
     * @return array<Action | ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('transcribe')
                    ->label(fn () => $this->getRecord()->transcript_status === JobStatus::Completed ? 'Re-transcribe' : 'Transcribe')
                    ->icon('heroicon-o-language')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->action(function () {
                        TranscribeVideo::dispatch($this->getRecord());

                        Notification::make()
                            ->title('Transcription queued')
                            ->body('Transcription has been dispatched.')
                            ->success()
                            ->send();
                    }),

                Action::make('convert_to_vertical')
                    ->label(fn () => $this->getRecord()->vertical_video_status === JobStatus::Completed ? 'Re-convert to Vertical' : 'Convert to Vertical')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->action(function () {
                        ConvertToVerticalVideo::dispatch($this->getRecord());

                        Notification::make()
                            ->title('Vertical conversion queued')
                            ->body('Vertical video conversion has been dispatched.')
                            ->success()
                            ->send();
                    }),

                Action::make('framing')
                    ->label('Framing')
                    ->icon('heroicon-o-viewfinder-circle')
                    ->color('primary')
                    ->visible(fn (): bool => $this->getRecord()->preview_frame_path !== null)
                    ->modalHeading('Video Framing')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('3xl')
                    ->modalContent(fn (Video $record): \Illuminate\Contracts\View\View => view(
                        'filament.resources.videos.video-framing-modal',
                        ['record' => $record]
                    )),

                DeleteAction::make()
                    ->modalDescription(fn (Video $record): string => $record->type === VideoType::Upload
                        ? 'This will delete the video record, all associated clips, and the uploaded video file.'
                        : 'This will delete the video record and all associated clips. The original video file will not be deleted.'
                    ),
            ])
                ->icon('heroicon-o-cog-6-tooth')
                ->label('')
                ->color('gray')
                ->button(),
        ];
    }

    public function updateVideoFraming(int $cropCenter): void
    {
        $video = $this->getRecord();

        $video->update(['vertical_video_crop_center' => $cropCenter]);
        ConvertToVerticalVideo::dispatch($video);

        Notification::make()
            ->title('Framing updated')
            ->body('Vertical video conversion has been re-queued.')
            ->success()
            ->send();

        $this->unmountAction();
    }

    /**
     * @return array{segments: list<array{start: float, end: float, text: string, speaker: string|null}>, clips: list<array{id: int, start: int, end: int}>, diarize: bool}
     */
    #[Computed]
    public function transcriptData(): array
    {
        $video = $this->getRecord();

        $segments = collect($video->transcript['segments'] ?? [])
            ->map(fn (array $s): array => [
                'start' => $s['start'],
                'end' => $s['end'],
                'text' => trim($s['text']),
                'speaker' => $s['speaker'] ?? null,
            ])->values()->all();

        return [
            'segments' => $segments,
            'clips' => $this->getClips(),
            'diarize' => $video->diarize,
        ];
    }

    /**
     * @return list<array{id: int, start: int, end: int}>
     */
    public function createClip(int $startSegmentIndex, int $endSegmentIndex): array
    {
        // Ensure start <= end
        if ($startSegmentIndex > $endSegmentIndex) {
            Log::warning('createClip called with start > end, swapping', [
                'video_id' => $this->getRecord()->id,
                'start_segment_index' => $startSegmentIndex,
                'end_segment_index' => $endSegmentIndex,
            ]);
            [$startSegmentIndex, $endSegmentIndex] = [$endSegmentIndex, $startSegmentIndex];
        }

        // Reject if start is within an existing clip
        $startInClip = $this->getRecord()->videoClips()
            ->where('start_segment_index', '<=', $startSegmentIndex)
            ->where('end_segment_index', '>=', $startSegmentIndex)
            ->exists();

        if ($startInClip) {
            Log::error('createClip called with start inside an existing clip', [
                'video_id' => $this->getRecord()->id,
                'start_segment_index' => $startSegmentIndex,
                'end_segment_index' => $endSegmentIndex,
            ]);

            return $this->getClips();
        }

        // Reject if clip would exceed 3 minutes
        $segments = $this->getRecord()->transcript['segments'] ?? [];

        if (! isset($segments[$startSegmentIndex], $segments[$endSegmentIndex])) {
            Log::error('createClip segment indices out of bounds', [
                'video_id' => $this->getRecord()->id,
                'start_segment_index' => $startSegmentIndex,
                'end_segment_index' => $endSegmentIndex,
                'segment_count' => count($segments),
            ]);

            return $this->getClips();
        }

        $duration = $segments[$endSegmentIndex]['end'] - $segments[$startSegmentIndex]['start'];
        if ($duration > 180) {
            Log::warning('createClip rejected: duration exceeds 180s', [
                'video_id' => $this->getRecord()->id,
                'start_segment_index' => $startSegmentIndex,
                'end_segment_index' => $endSegmentIndex,
                'duration' => $duration,
            ]);

            return $this->getClips();
        }

        // Truncate end if it would overlap into a following clip
        $nextClipStart = $this->getRecord()->videoClips()
            ->where('start_segment_index', '>', $startSegmentIndex)
            ->where('start_segment_index', '<=', $endSegmentIndex)
            ->min('start_segment_index');

        if ($nextClipStart !== null) {
            Log::warning('createClip truncating end to avoid overlapping a following clip', [
                'video_id' => $this->getRecord()->id,
                'start_segment_index' => $startSegmentIndex,
                'original_end_segment_index' => $endSegmentIndex,
                'truncated_end_segment_index' => $nextClipStart - 1,
            ]);
            $endSegmentIndex = $nextClipStart - 1;
        }

        $clip = $this->getRecord()->videoClips()->create([
            'start_segment_index' => $startSegmentIndex,
            'end_segment_index' => $endSegmentIndex,
        ]);

        GenerateVideoClipTitle::dispatch($clip);
        ExtractVideoClipVerticalVideo::dispatch($clip);

        unset($this->transcriptData);

        return $this->getClips();
    }

    /**
     * @return list<array{id: int, start: int, end: int}>
     */
    public function updateClip(int $clipId, int $startSegmentIndex, int $endSegmentIndex): array
    {
        $clip = $this->getRecord()->videoClips()->findOrFail($clipId);

        // Ensure start <= end
        if ($startSegmentIndex > $endSegmentIndex) {
            [$startSegmentIndex, $endSegmentIndex] = [$endSegmentIndex, $startSegmentIndex];
        }

        $segments = $this->getRecord()->transcript['segments'] ?? [];

        if (! isset($segments[$startSegmentIndex], $segments[$endSegmentIndex])) {
            Log::error('updateClip segment indices out of bounds', [
                'clip_id' => $clipId,
                'start_segment_index' => $startSegmentIndex,
                'end_segment_index' => $endSegmentIndex,
                'segment_count' => count($segments),
            ]);

            return $this->getClips();
        }

        // Reject if clip would exceed 3 minutes
        $duration = $segments[$endSegmentIndex]['end'] - $segments[$startSegmentIndex]['start'];
        if ($duration > 180) {
            Log::warning('updateClip rejected: duration exceeds 180s', [
                'clip_id' => $clipId,
                'duration' => $duration,
            ]);

            return $this->getClips();
        }

        // Check no overlap with other clips
        $overlapping = $this->getRecord()->videoClips()
            ->where('id', '!=', $clipId)
            ->where('start_segment_index', '<=', $endSegmentIndex)
            ->where('end_segment_index', '>=', $startSegmentIndex)
            ->exists();

        if ($overlapping) {
            Log::warning('updateClip rejected: overlaps with another clip', [
                'clip_id' => $clipId,
                'start_segment_index' => $startSegmentIndex,
                'end_segment_index' => $endSegmentIndex,
            ]);

            return $this->getClips();
        }

        $boundariesChanged = $clip->start_segment_index !== $startSegmentIndex
            || $clip->end_segment_index !== $endSegmentIndex;

        $clip->update([
            'start_segment_index' => $startSegmentIndex,
            'end_segment_index' => $endSegmentIndex,
        ]);

        if ($boundariesChanged) {
            GenerateVideoClipTitle::dispatch($clip);
        }

        ExtractVideoClipVerticalVideo::dispatch($clip);

        unset($this->transcriptData);

        return $this->getClips();
    }

    public function renameSpeakerAction(): Action
    {
        return Action::make('renameSpeaker')
            ->modalHeading('Rename speaker')
            ->modalWidth(Width::Small)
            ->schema([
                TextInput::make('name')
                    ->label('Speaker Name')
                    ->required(),
            ])
            ->fillForm(fn (array $arguments): array => [
                'name' => $this->getRecord()->speaker_names[$arguments['speaker']] ?? '',
            ])
            ->action(function (array $data, array $arguments): void {
                $video = $this->getRecord();
                $names = $video->speaker_names ?? [];
                $names[$arguments['speaker']] = $data['name'];
                $video->update(['speaker_names' => $names]);
                $this->speakerNames = $names;

                unset($this->transcriptData);
            });
    }

    /**
     * @return list<array{id: int, start: int, end: int}>
     */
    private function getClips(): array
    {
        return $this->getRecord()->videoClips()
            ->orderBy('start_segment_index')
            ->get(['id', 'start_segment_index', 'end_segment_index'])
            ->map(fn ($c): array => [
                'id' => $c->id,
                'start' => $c->start_segment_index,
                'end' => $c->end_segment_index,
            ])->values()->all();
    }
}
