<?php

namespace App\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\ServiceProvider;


use App\Models\Order;
use App\Observers\OrderObserver;

class AppServiceProvider extends ServiceProvider
{

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
        Order::observe(OrderObserver::class);
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
