<?php

namespace App\Filament\Resources\Videos;

use App\Enums\VideoType;
use App\Filament\Resources\Videos\Pages\CreateVideo;
use App\Filament\Resources\Videos\Pages\EditVideo;
use App\Filament\Resources\Videos\Pages\ListVideos;
use App\Filament\Resources\Videos\Tables\VideosTable;
use App\Models\Video;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
            Grid::make(2)->schema([
                Grid::make(1)->schema([
                    TextInput::make('title')
                        ->required(),
                    Toggle::make('diarize')
                        ->label('Enable speaker diarization')
                        ->helperText('Identifies different speakers in the transcript.')
                        ->default(false),
                ]),
                FileUpload::make('raw_video_path')
                    ->label('Video File')
                    ->disk('local')
                    ->directory('uploads')
                    ->acceptedFileTypes(['video/*'])
                    ->maxSize(1024 * 1024)
                    ->preserveFilenames()
                    ->required(),
            ])->visibleOn('create'),
            TextInput::make('title')
                ->visibleOn('edit'),
            Grid::make(2)->columnStart(1)->schema([
                TextInput::make('subtitle'),
                TextInput::make('scripture'),
            ])->visible(fn (?Video $record): bool => $record?->type === VideoType::Sermon),
            Grid::make(2)->schema([
                TextInput::make('preacher'),
                TextInput::make('color'),
            ])->visible(fn (?Video $record): bool => $record?->type === VideoType::Sermon),
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
            'create' => CreateVideo::route('/upload'),
            'edit' => EditVideo::route('/{record}/edit'),
        ];
    }
}
