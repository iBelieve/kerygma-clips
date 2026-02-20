<?php

namespace App\Filament\Resources\SermonVideos\Tables;

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
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'processing' => 'info',
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Added')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('date', 'desc')
            ->paginated([10]);
    }
}
