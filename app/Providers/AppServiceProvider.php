<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Allow large video uploads through Livewire's temporary upload endpoint.
        // The default is 12 MB which is too small for video files.
        config()->set('livewire.temporary_file_upload.rules', [
            'required', 'file', 'max:'.((int) (1024 * 1024)), // 1 GB
        ]);
    }
}
