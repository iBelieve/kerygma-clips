<?php

namespace App\Filament\Resources\VideoClips;

use App\Filament\Resources\VideoClips\Pages\EditVideoClip;
use App\Filament\Resources\VideoClips\Pages\ListVideoClips;
use App\Filament\Resources\VideoClips\Tables\VideoClipsTable;
use App\Models\VideoClip;
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

class VideoClipResource extends Resource
{
    protected static ?string $model = VideoClip::class;

    protected static ?string $modelLabel = 'Clip';

    protected static ?string $pluralModelLabel = 'Clips';

    protected static ?string $slug = 'clips';

    protected static ?int $navigationSort = 2;

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
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, VideoClip $record) {
                                $set('generated_description', $record->buildDescription(
                                    $get('excerpt') ?? '',
                                ));
                            })
                            ->hintAction(
                                Action::make('resetExcerpt')
                                    ->label('Reset to Transcript')
                                    ->icon(Heroicon::OutlinedArrowPath)
                                    ->action(function (Get $get, Set $set, VideoClip $record) {
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
                        ->formatStateUsing(fn (Get $get, VideoClip $record): string => $record->buildDescription(
                            $get('excerpt') ?? '',
                        ))
                        ->hintAction(
                            Action::make('copyDescription')
                                ->label('Copy')
                                ->icon(Heroicon::OutlinedClipboardDocument)
                                ->alpineClickHandler(<<<'JS'
                                    const text = $wire.get('data.generated_description')
                                    const showTooltip = () => $tooltip('Copied!', { theme: $store.theme, timeout: 2000 })
                                    if (navigator.clipboard) {
                                        navigator.clipboard.writeText(text).then(showTooltip)
                                    } else {
                                        const ta = document.createElement('textarea')
                                        ta.value = text
                                        ta.style.position = 'fixed'
                                        ta.style.left = '-9999px'
                                        document.body.appendChild(ta)
                                        ta.select()
                                        document.execCommand('copy')
                                        document.body.removeChild(ta)
                                        showTooltip()
                                    }
                                    JS)
                        ),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return VideoClipsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVideoClips::route('/'),
            'edit' => EditVideoClip::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
