<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL; // Ensure this is imported

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Enforce HTTPS exclusively in production environments
        if (app()->environment('production')) {
            URL::forceScheme('https');
            
            // Note: In newer Laravel versions, you can also use:
            // URL::forceHttps();
        }
    }
}
