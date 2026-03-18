<?php

namespace App\Filament\Pages;

use App\Models\Settings;
use App\Services\YouTubeService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * @property-read Schema $form
 */
class ManageSettings extends Page
{
    protected static ?string $navigationLabel = 'Settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 99;

    protected static ?string $title = 'Settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'call_to_action' => Settings::instance()->call_to_action,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Textarea::make('call_to_action')
                    ->label('Call to Action')
                    ->autosize(),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
                $this->getYouTubeSection(),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Settings::instance()->update($data);

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }

    protected function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment($this->getFormActionsAlignment()),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->submit('save'),
        ];
    }

    public function disconnectYouTube(): void
    {
        Settings::instance()->clearYouTubeConnection();

        Notification::make()
            ->title('YouTube disconnected')
            ->body('Your YouTube channel has been unlinked.')
            ->success()
            ->send();
    }

    protected function getYouTubeSection(): Section
    {
        $settings = Settings::instance();
        $connected = $settings->hasYouTubeConnection();

        if ($connected) {
            return Section::make('YouTube')
                ->icon('heroicon-o-video-camera')
                ->schema([
                    Text::make("Connected to **{$settings->youtube_channel_title}**"),
                ])
                ->footer([
                    Actions::make([
                        Action::make('disconnect_youtube')
                            ->label('Disconnect')
                            ->color('danger')
                            ->requiresConfirmation()
                            ->action(fn () => $this->disconnectYouTube()),
                    ]),
                ]);
        }

        $hasCredentials = config('services.google.client_id') && config('services.google.client_secret');

        if (! $hasCredentials) {
            return Section::make('YouTube')
                ->icon('heroicon-o-video-camera')
                ->schema([
                    Text::make('Set `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` in your `.env` file to enable YouTube integration.'),
                ]);
        }

        $authUrl = app(YouTubeService::class)->getAuthUrl();

        return Section::make('YouTube')
            ->icon('heroicon-o-video-camera')
            ->schema([
                Text::make('Connect your YouTube channel to automatically upload scheduled clips as YouTube Shorts.'),
            ])
            ->footer([
                Actions::make([
                    Action::make('connect_youtube')
                        ->label('Connect YouTube Channel')
                        ->color('primary')
                        ->url($authUrl),
                ]),
            ]);
    }
}
