<?php

namespace App\Filament\Resources\SermonClips;

use App\Filament\Resources\SermonClips\Pages\EditSermonClip;
use App\Filament\Resources\SermonClips\Pages\ListSermonClips;
use App\Filament\Resources\SermonClips\Tables\SermonClipsTable;
use App\Models\SermonClip;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SermonClipResource extends Resource
{
    protected static ?string $model = SermonClip::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScissors;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(2)->schema([
                    Group::make([
                        TextInput::make('title')
                            ->label('Title'),

                        Textarea::make('excerpt')
                            ->label('Excerpt')
                            ->autosize()
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (Get $get, Set $set, SermonClip $record) {
                                $set('generated_description', $record->buildDescription(
                                    $get('excerpt') ?? '',
                                ));
                            })
                            ->hintAction(
                                Action::make('resetExcerpt')
                                    ->label('Reset to Transcript')
                                    ->icon(Heroicon::OutlinedArrowPath)
                                    ->action(function (Get $get, Set $set, SermonClip $record) {
                                        $set('excerpt', $record->getTranscriptText());
                                        $set('generated_description', $record->buildDescription(
                                            $record->getTranscriptText(),
                                        ));
                                    })
                            ),
                    ]),

                    Textarea::make('generated_description')
                        ->label('Description')
                        ->extraAttributes(['class' => 'bg-white dark:bg-white/5'])
                        ->extraInputAttributes([
                            'class' => 'text-gray-950 dark:text-white',
                            'style' => '-webkit-text-fill-color: initial;',
                        ])
                        ->autosize()
                        ->disabled()
                        ->dehydrated(false)
                        ->formatStateUsing(fn (Get $get, SermonClip $record): string => $record->buildDescription(
                            $get('excerpt') ?? '',
                        ))
                        ->hintAction(
                            Action::make('copyDescription')
                                ->label('Copy')
                                ->icon(Heroicon::OutlinedClipboardDocument)
                                ->alpineClickHandler(<<<'JS'
                                    const text = $wire.get('data.generated_description')
                                    window.navigator.clipboard.writeText(text)
                                    $tooltip('Copied!', {
                                        theme: $store.theme,
                                        timeout: 2000,
                                    })
                                    JS)
                        ),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return SermonClipsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSermonClips::route('/'),
            'edit' => EditSermonClip::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
