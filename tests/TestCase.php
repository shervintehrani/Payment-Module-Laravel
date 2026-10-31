<?php

namespace PaymentModule\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Orchestra\Testbench\TestCase as Orchestra;
use PaymentModule\PaymentModuleServiceProvider;
use PaymentModule\Services\PaymentService;
use Shetabit\Multipay\Payment as Multipay;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [PaymentModuleServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Routes are registered while the provider boots, so route related
        // settings must be defined here. Auth is switched off for the form
        // and initiate endpoints; history keeps its own "auth" middleware.
        $app['config']->set('payment-module.routes.auth_middleware', []);
        $app['config']->set('payment-module.default_gateway', 'zarinpal');
    }

    /**
     * Replace the real Multipay with a mock so no HTTP request leaves the
     * test suite. The mock is returned so tests can add expectations.
     */
    protected function fakeGateway(): MockInterface
    {
        $gateway = Mockery::mock(Multipay::class);
        $gateway->shouldReceive('via')->andReturnSelf();
        $gateway->shouldReceive('callbackUrl')->andReturnSelf();
        $gateway->shouldReceive('amount')->andReturnSelf();
        $gateway->shouldReceive('transactionId')->andReturnSelf();

        $this->app->bind(
            PaymentService::class,
            fn ($app) => new PaymentService($app['config']->get('payment-module'), fn () => $gateway)
        );

        return $gateway;
    }
}
