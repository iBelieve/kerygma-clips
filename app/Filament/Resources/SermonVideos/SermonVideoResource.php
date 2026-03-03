<?php

namespace App\Filament\Resources\SermonVideos;

use App\Filament\Resources\SermonVideos\Pages\EditSermonVideo;
use App\Filament\Resources\SermonVideos\Pages\ListSermonVideos;
use App\Filament\Resources\SermonVideos\Tables\SermonVideosTable;
use App\Models\SermonVideo;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SermonVideoResource extends Resource
{
    protected static ?string $model = SermonVideo::class;

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
        return SermonVideosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSermonVideos::route('/'),
            'edit' => EditSermonVideo::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
