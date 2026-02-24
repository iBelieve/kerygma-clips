<?php

namespace App\Filament\Resources\SermonClips;

use App\Filament\Resources\SermonClips\Pages\ListSermonClips;
use App\Filament\Resources\SermonClips\Tables\SermonClipsTable;
use App\Models\SermonClip;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SermonClipResource extends Resource
{
    protected static ?string $model = SermonClip::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScissors;

    public static function table(Table $table): Table
    {
        return SermonClipsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSermonClips::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
