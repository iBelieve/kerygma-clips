<?php

namespace App\Filament\Resources\Videos\Tables;

use App\Enums\JobStatus;
use App\Enums\VideoType;
use App\Models\Video;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VideosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->label('Date & Time')
                    ->dateTime('D, M j, Y g:i A')
                    ->timezone('America/Chicago')
                    ->sortable(),

                TextColumn::make('title')
                    ->label('Title')
                    ->placeholder("\u{2014}")
                    ->searchable(),

                TextColumn::make('subtitle')
                    ->label('Subtitle')
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
                    ->tooltip(function (Video $record): ?string {
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
                    ->tooltip(function (Video $record): ?string {
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
                ActionGroup::make([
                    DeleteAction::make()
                        ->modalDescription(fn (Video $record): string => $record->type === VideoType::Upload
                            ? 'This will delete the video record, all associated clips, and the uploaded video file.'
                            : 'This will delete the video record and all associated clips. The original video file will not be deleted.'
                        ),
                ])
                    ->color('gray'),
            ])
            ->defaultSort('date', 'desc')
            ->paginated([10]);
    }
}
