<?php

namespace App\Http\Controllers;

use App\Services\YouTubeService;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class YouTubeController extends Controller
{
    public function callback(Request $request, YouTubeService $youtube): RedirectResponse
    {
        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            Notification::make()
                ->title('YouTube connection failed')
                ->body('No authorization code received.')
                ->danger()
                ->send();

            return redirect()->to('/settings');
        }

        try {
            $youtube->handleCallback($code);

            Notification::make()
                ->title('YouTube connected')
                ->body('Your YouTube channel has been linked successfully.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('YouTube connection failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        return redirect()->to('/settings');
    }
}
