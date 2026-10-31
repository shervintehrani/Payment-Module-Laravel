<?php

use Illuminate\Support\Facades\Route;
use PaymentModule\Http\Controllers\PaymentController;

/*
|--------------------------------------------------------------------------
| Payment Module routes
|--------------------------------------------------------------------------
|
| Prefix and middleware come from config/payment-module.php. Route names are
| stable (payment.form, payment.initiate, payment.callback, payment.history)
| so they can be used with route() in application code.
|
*/

// The callback must not depend on the CSRF token: gateways may return the
// customer with a cross-site POST. Both the Laravel 10 and 11+ class names
// are listed, only those that exist are excluded.
$csrfMiddleware = array_values(array_filter([
    \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
    'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken',
], 'class_exists'));

Route::middleware(config('payment-module.routes.middleware', ['web']))
    ->prefix(config('payment-module.routes.prefix', 'payment'))
    ->group(function () use ($csrfMiddleware) {

        // Customer facing pages, optionally protected by auth.
        Route::middleware(config('payment-module.routes.auth_middleware', []))->group(function () {
            Route::get('/', [PaymentController::class, 'form'])->name('payment.form');
            Route::post('initiate', [PaymentController::class, 'initiate'])->name('payment.initiate');
        });

        // Gateway return URL, identified by transaction id (no auth, no CSRF).
        Route::match(['get', 'post'], 'callback', [PaymentController::class, 'callback'])
            ->withoutMiddleware($csrfMiddleware)
            ->name('payment.callback');

        // Always private: a user only ever sees their own payments.
        Route::get('history', [PaymentController::class, 'history'])
            ->middleware('auth')
            ->name('payment.history');
    });
