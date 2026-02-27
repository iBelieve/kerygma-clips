<?php

namespace App\Filament\Resources\SermonClips\Tables;

use App\Enums\JobStatus;
use App\Jobs\ExtractSermonClipVerticalVideo;
use App\Jobs\GenerateSermonClipTitle;
use App\Jobs\PublishSermonClipToFacebook;
use App\Models\SermonClip;
use App\Services\FacebookReelsService;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
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

                TextColumn::make('fb_reel_status')
                    ->label('Facebook')
                    ->badge()
                    ->color(fn (JobStatus $state): string => match ($state) {
                        JobStatus::Pending => 'warning',
                        JobStatus::Processing => 'info',
                        JobStatus::Completed => 'success',
                        JobStatus::Failed, JobStatus::TimedOut => 'danger',
                    })
                    ->tooltip(function (SermonClip $record): ?string {
                        if ($record->fb_reel_status === JobStatus::Failed) {
                            return $record->fb_reel_error;
                        }

                        return null;
                    }),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('generate_title')
                    ->label('Generate Title')
                    ->icon('heroicon-o-sparkles')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function (SermonClip $record) {
                        GenerateSermonClipTitle::dispatch($record);

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
                    ->visible(fn (SermonClip $record): bool => $record->clip_video_status !== JobStatus::Completed)
                    ->requiresConfirmation()
                    ->action(function (SermonClip $record) {
                        ExtractSermonClipVerticalVideo::dispatch($record);

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
                    ->visible(fn (SermonClip $record): bool => $record->canPublishToFacebook())
                    ->schema([
                        Textarea::make('fb_reel_description')
                            ->label('Caption')
                            ->default(fn (SermonClip $record): ?string => $record->title)
                            ->rows(3),
                        DateTimePicker::make('fb_reel_scheduled_for')
                            ->label('Schedule for (optional)')
                            ->native(false)
                            ->minDate(now()->addMinutes(10))
                            ->timezone('America/Chicago'),
                    ])
                    ->action(function (SermonClip $record, array $data) {
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

                Action::make('check_reel_status')
                    ->label('Check Reel Status')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn (SermonClip $record): bool => $record->fb_reel_status === JobStatus::Completed && $record->fb_reel_id !== null)
                    ->action(function (SermonClip $record) {
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

                Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (SermonClip $record) => $record->delete()),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10]);
    }
}
