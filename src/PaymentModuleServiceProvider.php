<?php

declare(strict_types=1);

namespace PaymentModule;

use Illuminate\Support\ServiceProvider;
use PaymentModule\Services\PaymentService;

class PaymentModuleServiceProvider extends ServiceProvider
{
    /**
     * Register bindings. Runs before every provider has booted, so it must
     * not touch routes, views or other providers' services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/payment-module.php', 'payment-module');

        // bind(), not singleton(): the service is stateless, and a fresh
        // instance always sees the configuration of the current test/request.
        $this->app->bind(
            PaymentService::class,
            fn ($app) => new PaymentService($app['config']->get('payment-module'))
        );
    }

    public function boot(): void
    {
        // Migrations are loaded straight from the package. Do not publish
        // them as well, or the table would be created twice.
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'payment-module');

        if (config('payment-module.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/payment-module.php' => config_path('payment-module.php'),
            ], 'payment-module-config');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/payment-module'),
            ], 'payment-module-views');
        }
    }
}
