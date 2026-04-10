<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Teller\TellerClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TellerClient::class, fn () => new TellerClient(
            certPath: config('teller.cert_path'),
            keyPath: config('teller.key_path'),
            baseUrl: config('teller.base_url', 'https://api.teller.io'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
