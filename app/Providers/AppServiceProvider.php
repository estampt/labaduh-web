<?php

namespace App\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    public function register(): void
    {
        $this->app->bind(ClientInterface::class, function () {
            return new Client([
                'timeout' => 10,
            ]);
        });
    }

}
