<?php

namespace App\Filament\Resources\SermonVideos\Tables;

use App\Enums\TranscriptStatus;
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
                    ->color(fn (TranscriptStatus $state): string => match ($state) {
                        TranscriptStatus::Pending => 'warning',
                        TranscriptStatus::Processing => 'info',
                        TranscriptStatus::Completed => 'success',
                        TranscriptStatus::Failed => 'danger',
                    })
                    ->tooltip(function (SermonVideo $record): ?string {
                        if ($record->transcript_status !== TranscriptStatus::Completed) {
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
                    ->visible(fn (SermonVideo $record): bool => $record->transcript_status !== TranscriptStatus::Completed)
                    ->requiresConfirmation()
                    ->action(function (SermonVideo $record) {
                        TranscribeSermonVideo::dispatch($record);

                        Notification::make()
                            ->title('Transcription queued')
                            ->body('Transcription has been dispatched.')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('date', 'desc')
            ->paginated([10]);
    }
}
