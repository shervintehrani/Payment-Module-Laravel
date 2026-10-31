# Payment Module for Laravel

[![Tests](https://github.com/shervintehrani/Payment-Module-Laravel/actions/workflows/tests.yml/badge.svg)](https://github.com/shervintehrani/Payment-Module-Laravel/actions/workflows/tests.yml)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-777bb4)
![Laravel](https://img.shields.io/badge/Laravel-10%20%7C%2011%20%7C%2012-ff2d20)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

A small, opinionated Laravel package that stores payments in your database and talks to Iranian payment
gateways through [Shetabit Multipay](https://github.com/shetabit/multipay). It gives you the whole
payment life cycle (create, redirect to the bank, verify the callback) behind a tiny service class, plus
ready-made routes, views, events, a factory and a seeder.

## Table of contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [How it works](#how-it-works)
- [Routes](#routes)
- [Usage](#usage)
- [Events](#events)
- [Customising views](#customising-views)
- [Adding another gateway](#adding-another-gateway)
- [Testing](#testing)
- [Docker](#docker)
- [Project structure](#project-structure)
- [Security notes](#security-notes)
- [Upgrading](#upgrading)
- [Contributing](#contributing)
- [License](#license)

## Features

- **Complete payment flow**: `create()` → `pay()` → `verify()` in one service class.
- **Multiple gateways** via Shetabit Multipay (Zarinpal pre-configured, plus Multipay's offline `local`
  test gateway). Any other Multipay driver is a config entry away.
- **Safe verification**: idempotent, row-locked (no double fulfilment on duplicate callbacks) and tolerant of
  gateway timeouts (the payment stays `pending` and the callback can be retried).
- **Typed status** (`PaymentStatus` enum): `pending`, `success`, `failed`.
- **Events**: `PaymentSucceeded` and `PaymentFailed`, dispatched exactly once per payment.
- **Routes and views included**: payment form, callback, result page and per-user history. Everything can be
  disabled or replaced.
- **Developer friendly**: Eloquent factory with states, seeder, PHPUnit/Testbench suite, Docker setup and CI.

## Requirements

| Dependency          | Version                |
|---------------------|------------------------|
| PHP                 | 8.1 or higher          |
| Laravel             | 10.x, 11.x or 12.x     |
| `shetabit/multipay` | ^2.10 (installed automatically) |

## Installation

```bash
composer require shervintehrani/payment-module-laravel
```

The service provider is registered through Laravel's package auto-discovery. Then run the migrations; the
package loads its own, so there is nothing to publish:

```bash
php artisan migrate
```

Optionally publish the configuration and the views:

```bash
php artisan vendor:publish --tag=payment-module-config
php artisan vendor:publish --tag=payment-module-views
```

### Installing from a local path

While developing, point Composer at your checkout in your application's `composer.json`:

```json
"repositories": [
    { "type": "path", "url": "../payment-module-laravel", "options": { "symlink": true } }
]
```

```bash
composer require shervintehrani/payment-module-laravel:@dev
```

## Configuration

Add the gateway credentials to your `.env`:

```dotenv
PAYMENT_MODULE_GATEWAY=zarinpal
ZARINPAL_MERCHANT_ID=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
ZARINPAL_MODE=normal        # normal | sandbox
ZARINPAL_CURRENCY=T         # T = Toman, R = Rial
```

All options live in [`config/payment-module.php`](config/payment-module.php):

| Option / environment variable                  | Default       | Description |
|-----------------------------------------------|---------------|-------------|
| `default_gateway` / `PAYMENT_MODULE_GATEWAY`   | `zarinpal`    | Gateway used when none is given. Must exist in `gateways`. |
| `table`                                        | `payments`    | Table name. Change it **before** the first migration. |
| `min_amount` / `PAYMENT_MODULE_MIN_AMOUNT`     | `1000`        | Smallest amount accepted by the built-in endpoint. |
| `routes.enabled`                               | `true`        | Set to `false` to register your own routes. |
| `routes.prefix` / `PAYMENT_MODULE_ROUTE_PREFIX`| `payment`     | URL prefix of the package routes. |
| `routes.middleware`                            | `['web']`     | Applied to every package route. |
| `routes.auth_middleware` / `PAYMENT_MODULE_REQUIRE_AUTH` | `['auth']` | Applied to the form and initiate endpoints. Set the variable to `false` to allow guest payments. |
| `callback_url` / `PAYMENT_MODULE_CALLBACK_URL` | `null`        | Absolute callback URL. Defaults to the `payment.callback` route. |
| `callback_transaction_keys`                    | see file      | Request parameters that may carry the gateway transaction id. |
| `gateways`                                     | `zarinpal`, `local` | Multipay driver settings. |
| `map`                                          | `[]`          | Extra Multipay driver classes. |

> **Amounts** are stored as integers in the gateway currency. With `ZARINPAL_CURRENCY=T` an amount of `50000`
> means 50,000 Toman; the driver converts to Rial for the bank.

## How it works

```
 Customer                 Your app (this package)                 Bank / gateway
    │  POST /payment/initiate   │                                        │
    │ ─────────────────────────▶│ create()  → payment "pending"          │
    │                           │ pay()     → purchase request ─────────▶│
    │                           │           ◀──── transaction id ────────│
    │ ◀── redirect to bank ─────│ (transaction id saved on the payment)  │
    │ ─────────────────────────────────────── pays at the bank ─────────▶│
    │ ◀──────────── redirect to /payment/callback?Authority=… ───────────│
    │ ─────────────────────────▶│ verify()  → ask the bank ─────────────▶│
    │                           │           ◀──── receipt / rejection ───│
    │ ◀── result page ──────────│ payment "success" or "failed" + event  │
```

A payment moves through three states and never leaves a final one:

```
pending ──verified──▶ success
   └────rejected────▶ failed
```

## Routes

Names are stable, so you can use them with `route()`.

| Method     | URI                 | Name               | Auth                  | Purpose |
|------------|---------------------|--------------------|-----------------------|---------|
| `GET`      | `/payment`          | `payment.form`     | `auth_middleware`     | Payment form. |
| `POST`     | `/payment/initiate` | `payment.initiate` | `auth_middleware`     | Create the payment and redirect to the bank. Accepts JSON too. |
| `GET/POST` | `/payment/callback` | `payment.callback` | none, CSRF-exempt     | Where the bank returns the customer. |
| `GET`      | `/payment/history`  | `payment.history`  | always `auth`         | The signed-in user's own payments. |

`POST /payment/initiate` parameters: `amount` (integer, required) and `gateway` (optional, must be configured).

## Usage

### Use the built-in flow

Link to `route('payment.form')`, or post a form yourself:

```blade
<form method="POST" action="{{ route('payment.initiate') }}">
    @csrf
    <input type="number" name="amount" min="1000" required>
    <button type="submit">Pay</button>
</form>
```

### Use the service directly

Disable the package routes (`routes.enabled = false`) and drive the flow from your own controllers. The
callback URL still has to point to a route that calls `verify()`; set `callback_url` accordingly.

```php
use PaymentModule\Exceptions\PaymentException;
use PaymentModule\Services\PaymentService;

class CheckoutController
{
    public function pay(Request $request, PaymentService $payments)
    {
        $payment = $payments->create(
            amount: $order->total,
            userId: $request->user()->id,
            gateway: 'zarinpal',            // optional, defaults to default_gateway
        );

        try {
            $form = $payments->pay($payment);   // Shetabit\Multipay\RedirectionForm
        } catch (PaymentException $e) {
            report($e);
            return back()->withErrors('Payment could not be started.');
        }

        return $form->getMethod() === 'GET'
            ? redirect()->away($form->getAction())
            : response($form->render());        // auto-submitting HTML form for POST gateways
    }

    public function callback(Request $request, PaymentService $payments)
    {
        $payment = $payments->findFromRequest($request) ?? abort(404);

        $payment = $payments->verify($payment); // safe to call more than once

        return $payment->isSuccessful()
            ? redirect()->route('orders.thanks')
            : redirect()->route('orders.failed');
    }
}
```

`verify()` throws `PaymentException` only for technical problems (timeout, gateway down). A payment the bank
rejects is **not** an exception: it is returned with status `failed`.

### Query payments

```php
use PaymentModule\Models\Payment;

Payment::successful()->where('user_id', $id)->sum('amount');
Payment::pending()->where('created_at', '<', now()->subDay())->get();

$payment->isPending();   // isSuccessful(), isFailed()
$payment->user;          // BelongsTo, resolved from auth.providers.users.model
```

### Factory and seeder

```php
Payment::factory()->count(10)->create();                    // pending
Payment::factory()->successful()->forUser($user->id)->create();
Payment::factory()->failed()->create();
```

```bash
php artisan db:seed --class="PaymentModule\Database\Seeders\PaymentSeeder"
```

The seeder creates 15 payments (5 pending, 5 successful, 5 failed). It needs `fakerphp/faker`, which Laravel
ships as a dev dependency.

## Events

| Event                                      | Dispatched when |
|--------------------------------------------|-----------------|
| `PaymentModule\Events\PaymentSucceeded`    | A payment has just been verified successfully. |
| `PaymentModule\Events\PaymentFailed`       | A payment has just been marked as failed. |

Both expose a public `$payment` property. Use them to fulfil orders and send receipts:

```php
Event::listen(PaymentSucceeded::class, function (PaymentSucceeded $event) {
    Order::where('payment_id', $event->payment->id)->first()?->markAsPaid();
});
```

Events fire once per payment: repeated callbacks for an already settled payment do not dispatch them again.

## Customising views

```bash
php artisan vendor:publish --tag=payment-module-views
```

The views are copied to `resources/views/vendor/payment-module` (`layout`, `initiate`, `result`, `history`).
Change `layout.blade.php` to extend your application layout.

## Adding another gateway

1. Add its Multipay settings under `gateways` in `config/payment-module.php` (see the
   [Multipay documentation](https://github.com/shetabit/multipay) for the keys each driver needs).
2. Add the request parameter the gateway calls back with to `callback_transaction_keys`.
3. Select it with `PAYMENT_MODULE_GATEWAY`, pass `gateway` to `create()`, or send `gateway` to the initiate endpoint.

To use a driver of your own, register it in `map`:

```php
'map' => ['mybank' => \App\Payment\MyBankDriver::class],
```

## Testing

The suite runs on [Orchestra Testbench](https://github.com/orchestral/testbench) with an in-memory SQLite
database. The gateway is mocked, so no network access is needed.

```bash
composer install
composer test
```

## Docker

You only need Docker. Nothing is installed on your machine.

```bash
# Run the test suite
docker compose run --rm app          # or: make test

# Try the package in a real Laravel 11 app at http://localhost:8000/payment
docker compose up --build demo       # or: make demo
```

- **`app`** installs the dependencies into the mounted folder and runs PHPUnit.
- **`demo`** builds a fresh Laravel application, installs this package from your working copy (symlinked, so
  edits are picked up immediately), migrates, seeds sample payments and serves it. It uses Multipay's offline
  **`local`** gateway by default, so you can complete or cancel a payment without a bank account.

Copy `.env.example` to `.env` to change the port, switch to `zarinpal` or set a merchant id. The demo allows guest
payments; `/payment/history` still requires a login, which the bare demo app does not provide.

Other shortcuts: `make shell` (shell in the dev container), `make clean` (remove containers and volumes).

## Project structure

```
.
├── config/payment-module.php         Package configuration
├── database/
│   ├── factories/PaymentFactory.php
│   ├── migrations/                   payments table
│   └── seeders/PaymentSeeder.php
├── resources/views/                  layout, initiate, result, history
├── routes/web.php
├── src/
│   ├── Enums/PaymentStatus.php
│   ├── Events/                       PaymentSucceeded, PaymentFailed
│   ├── Exceptions/PaymentException.php
│   ├── Http/Controllers/PaymentController.php
│   ├── Http/Requests/InitiatePaymentRequest.php
│   ├── Models/Payment.php
│   ├── Services/PaymentService.php   Payment life cycle + Multipay integration
│   └── PaymentModuleServiceProvider.php
├── tests/                            PHPUnit + Testbench
├── docker/ · Dockerfile · docker-compose.yml · Makefile
└── .github/workflows/tests.yml       CI matrix (PHP 8.1-8.3, Laravel 10-12)
```

## Security notes

- **Never trust the browser for the result.** The callback only identifies the payment; success is decided by
  the server-to-server `verify()` call, using the amount stored in your database, not a request parameter.
- **Callbacks are idempotent and locked**, so replaying or racing the callback cannot fulfil an order twice.
- **Gateway errors are logged, not displayed.** Customers see a generic message; the reason is stored in
  `failure_reason` and reported through Laravel's exception handler.
- **History is scoped to the signed-in user.** Keep it that way if you customise the controller.
- Keep merchant ids and secrets in `.env`, never in version control.
- Always test against the gateway's sandbox before going live, and review Multipay's driver notes for your bank.

## Upgrading

See [CHANGELOG.md](CHANGELOG.md). The unreleased version changes the database schema, routes and namespaces
of the original prototype, so read the *Changed (breaking)* section before upgrading an existing installation.

## Contributing

1. Fork the repository and create a feature branch.
2. Add or update tests; run `make test` (or `composer test`).
3. Open a pull request describing the change.

## License

Released under the [MIT License](LICENSE).
