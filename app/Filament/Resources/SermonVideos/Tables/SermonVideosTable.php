<?php

namespace App\Filament\Resources\SermonVideos\Tables;

use App\Enums\JobStatus;
use App\Jobs\ConvertToVerticalVideo;
use App\Jobs\TranscribeSermonVideo;
use App\Models\SermonVideo;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SermonVideosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->label('Date & Time')
                    ->dateTime('M j, Y g:i A')
                    ->timezone('America/Chicago')
                    ->sortable(),

                TextColumn::make('title')
                    ->label('Title')
                    ->placeholder("\u{2014}")
                    ->searchable(),

                TextColumn::make('duration')
                    ->label('Duration')
                    ->placeholder("\u{2014}")
                    ->formatStateUsing(function (?int $state): ?string {
                        if ($state === null) {
                            return null;
                        }

                        $hours = intdiv($state, 3600);
                        $minutes = intdiv($state % 3600, 60);
                        $seconds = $state % 60;

                        if ($hours > 0) {
                            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
                        }

                        return sprintf('%d:%02d', $minutes, $seconds);
                    })
                    ->sortable(),

                TextColumn::make('transcript_status')
                    ->label('Transcript')
                    ->badge()
                    ->color(fn (JobStatus $state): string => match ($state) {
                        JobStatus::Pending => 'warning',
                        JobStatus::Processing => 'info',
                        JobStatus::Completed => 'success',
                        JobStatus::Failed, JobStatus::TimedOut => 'danger',
                    })
                    ->tooltip(function (SermonVideo $record): ?string {
                        if ($record->transcript_status !== JobStatus::Completed) {
                            return null;
                        }

                        $duration = $record->transcription_duration;

                        if ($duration === null) {
                            return null;
                        }

                        $minutes = intdiv($duration, 60);
                        $seconds = $duration % 60;

                        return sprintf('Transcription completed in %dm %02ds', $minutes, $seconds);
                    }),

                TextColumn::make('vertical_video_status')
                    ->label('Vertical')
                    ->badge()
                    ->color(fn (JobStatus $state): string => match ($state) {
                        JobStatus::Pending => 'warning',
                        JobStatus::Processing => 'info',
                        JobStatus::Completed => 'success',
                        JobStatus::Failed, JobStatus::TimedOut => 'danger',
                    })
                    ->tooltip(function (SermonVideo $record): ?string {
                        if ($record->vertical_video_status !== JobStatus::Completed) {
                            return null;
                        }

                        $duration = $record->vertical_video_duration;

                        if ($duration === null) {
                            return null;
                        }

                        $minutes = intdiv($duration, 60);
                        $seconds = $duration % 60;

                        return sprintf('Conversion completed in %dm %02ds', $minutes, $seconds);
                    }),

                TextColumn::make('created_at')
                    ->label('Added')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('transcribe')
                    ->label('Transcribe')
                    ->icon('heroicon-o-language')
                    ->color('primary')
                    ->visible(fn (SermonVideo $record): bool => $record->transcript_status !== JobStatus::Completed)
                    ->requiresConfirmation()
                    ->action(function (SermonVideo $record) {
                        TranscribeSermonVideo::dispatch($record);

                        Notification::make()
                            ->title('Transcription queued')
                            ->body('Transcription has been dispatched.')
                            ->success()
                            ->send();
                    }),

                Action::make('convert_to_vertical')
                    ->label('Convert to Vertical')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->color('primary')
                    ->visible(fn (SermonVideo $record): bool => $record->vertical_video_status !== JobStatus::Completed)
                    ->requiresConfirmation()
                    ->action(function (SermonVideo $record) {
                        ConvertToVerticalVideo::dispatch($record);

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
                    ->visible(fn (SermonVideo $record): bool => $record->preview_frame_path !== null)
                    ->modalHeading('Video Framing')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('3xl')
                    ->modalContent(fn (SermonVideo $record): \Illuminate\Contracts\View\View => view(
                        'filament.resources.sermon-videos.video-framing-modal',
                        ['record' => $record]
                    )),
            ])
            ->defaultSort('date', 'desc')
            ->paginated([10]);
    }
}
