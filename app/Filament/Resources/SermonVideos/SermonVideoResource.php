<?php

namespace App\Filament\Resources\SermonVideos;

use App\Filament\Resources\SermonVideos\Pages\ListSermonVideos;
use App\Filament\Resources\SermonVideos\Tables\SermonVideosTable;
use App\Models\SermonVideo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SermonVideoResource extends Resource
{
    protected static ?string $model = SermonVideo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedVideoCamera;

    public static function table(Table $table): Table
    {
        return SermonVideosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSermonVideos::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
