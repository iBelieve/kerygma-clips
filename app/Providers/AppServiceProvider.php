<?php

namespace App\Providers;

use App\Services\FacebookReelsService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FacebookReelsService::class, function () {
            return new FacebookReelsService(
                pageId: (string) config('services.facebook.page_id'),
                pageAccessToken: (string) config('services.facebook.page_access_token'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
