<?php

namespace Cbu\Currency;

use Cbu\Currency\Console\Commands\FetchCurrencyRatesCommand;
use Cbu\Currency\Console\Commands\SyncCurrenciesCommand;
use Cbu\Currency\Services\CbuApiService;
use Illuminate\Support\ServiceProvider;

class CbuCurrencyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/cbu-currency.php', 'cbu-currency'
        );

        $this->app->singleton('cbu-currency', function ($app) {
            return new CbuCurrency();
        });

        $this->app->singleton(CbuApiService::class, function ($app) {
            return new CbuApiService();
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                FetchCurrencyRatesCommand::class,
                SyncCurrenciesCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/cbu-currency.php' => config_path('cbu-currency.php'),
            ], 'cbu-currency-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'cbu-currency-migrations');
        }
    }
}
