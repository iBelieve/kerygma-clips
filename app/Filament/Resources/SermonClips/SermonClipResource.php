<?php

namespace App\Filament\Resources\SermonClips;

use App\Filament\Resources\SermonClips\Pages\EditSermonClip;
use App\Filament\Resources\SermonClips\Pages\ListSermonClips;
use App\Filament\Resources\SermonClips\Tables\SermonClipsTable;
use App\Models\SermonClip;
use App\Models\SermonVideo;
use App\Models\Setting;
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
            ->components([
                Grid::make(2)->schema([
                    Group::make([
                        TextInput::make('title')
                            ->label('Title')
                            ->live(debounce: 500),

                        Textarea::make('excerpt')
                            ->label('Excerpt')
                            ->autosize()
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (Get $get, Set $set, SermonClip $record) {
                                $set('generated_description', static::buildDescription(
                                    $get('excerpt') ?? '',
                                    $record->sermonVideo,
                                ));
                            })
                            ->hintAction(
                                Action::make('resetExcerpt')
                                    ->label('Reset to Transcript')
                                    ->icon(Heroicon::OutlinedArrowPath)
                                    ->action(function (Get $get, Set $set, SermonClip $record) {
                                        $set('excerpt', $record->getTranscriptText());
                                        $set('generated_description', static::buildDescription(
                                            $record->getTranscriptText(),
                                            $record->sermonVideo,
                                        ));
                                    })
                            ),
                    ]),

                    Textarea::make('generated_description')
                        ->label('Description')
                        ->autosize()
                        ->disabled()
                        ->dehydrated(false)
                        ->formatStateUsing(fn (Get $get, SermonClip $record): string => static::buildDescription(
                            $get('excerpt') ?? '',
                            $record->sermonVideo,
                        )),
                ]),
            ]);
    }

    public static function buildDescription(string $excerpt, SermonVideo $sermonVideo): string
    {
        $subtitle = $sermonVideo->getNormalizedSubtitle();
        $scripture = $sermonVideo->scripture;
        $preacher = $sermonVideo->preacher;
        $callToAction = Setting::instance()->call_to_action;

        $lines = [$excerpt, ''];

        $sermonLine = 'Clip from a sermon';
        if ($scripture) {
            $sermonLine .= " on {$scripture}";
        }
        if ($subtitle) {
            $sermonLine .= " for {$subtitle}";
        }
        if ($preacher) {
            $sermonLine .= " by {$preacher}";
        }
        $sermonLine .= '.';

        $lines[] = $sermonLine;

        if ($callToAction) {
            $lines[] = '';
            $lines[] = $callToAction;
        }

        return implode("\n", $lines);
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
