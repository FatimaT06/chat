<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\File;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        if (env('FIREBASE_CREDENTIALS')) {

            $path = storage_path('app/firebase-credentials.json');

            if (!File::exists($path)) {
                File::put($path, env('FIREBASE_CREDENTIALS'));
            }
        }
    }
}