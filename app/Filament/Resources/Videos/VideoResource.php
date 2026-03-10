<?php

namespace App\Filament\Resources\Videos;

use App\Filament\Resources\Videos\Pages\EditVideo;
use App\Filament\Resources\Videos\Pages\ListVideos;
use App\Filament\Resources\Videos\Tables\VideosTable;
use App\Models\Video;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VideoResource extends Resource
{
    protected static ?string $model = Video::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedVideoCamera;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title'),
            Grid::make(2)->columnStart(1)->schema([
                TextInput::make('subtitle'),
                TextInput::make('scripture'),
            ]),
            Grid::make(2)->schema([
                TextInput::make('preacher'),
                TextInput::make('color'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return VideosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVideos::route('/'),
            'edit' => EditVideo::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
