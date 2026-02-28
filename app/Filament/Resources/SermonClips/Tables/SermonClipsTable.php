<?php

namespace App\Filament\Resources\SermonClips\Tables;

use App\Enums\JobStatus;
use App\Models\SermonClip;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SermonClipsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sermonVideo.date')
                    ->label('Sermon Date')
                    ->dateTime('M j, Y g:i A')
                    ->timezone('America/Chicago')
                    ->sortable(),

                TextColumn::make('title')
                    ->label('Title')
                    ->placeholder("\u{2014}")
                    ->searchable(),

                TextColumn::make('starts_at')
                    ->label('Start Time')
                    ->placeholder("\u{2014}")
                    ->formatStateUsing(function (?float $state): ?string {
                        if ($state === null) {
                            return null;
                        }

                        $totalSeconds = (int) round($state);
                        $hours = intdiv($totalSeconds, 3600);
                        $minutes = intdiv($totalSeconds % 3600, 60);
                        $seconds = $totalSeconds % 60;

                        if ($hours > 0) {
                            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
                        }

                        return sprintf('%d:%02d', $minutes, $seconds);
                    })
                    ->sortable(),

                TextColumn::make('duration')
                    ->label('Duration')
                    ->placeholder("\u{2014}")
                    ->formatStateUsing(function (?float $state): ?string {
                        if ($state === null) {
                            return null;
                        }

                        $totalSeconds = (int) round($state);
                        $minutes = intdiv($totalSeconds, 60);
                        $seconds = $totalSeconds % 60;

                        return sprintf('%d:%02d', $minutes, $seconds);
                    })
                    ->sortable(),

                TextColumn::make('clip_video_status')
                    ->label('Video')
                    ->badge()
                    ->color(fn (JobStatus $state): string => match ($state) {
                        JobStatus::Pending => 'warning',
                        JobStatus::Processing => 'info',
                        JobStatus::Completed => 'success',
                        JobStatus::Failed, JobStatus::TimedOut => 'danger',
                    })
                    ->tooltip(function (SermonClip $record): ?string {
                        if ($record->clip_video_status !== JobStatus::Completed) {
                            return null;
                        }

                        $duration = $record->clip_video_duration;

                        if ($duration === null) {
                            return null;
                        }

                        $minutes = intdiv($duration, 60);
                        $seconds = $duration % 60;

                        return sprintf('Extraction completed in %dm %02ds', $minutes, $seconds);
                    }),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10]);
    }
}
