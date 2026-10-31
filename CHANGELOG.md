# Changelog

All notable changes to this project are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Changed (breaking)
- Namespaces and folders now follow PSR-4: `src/Http/Controllers`, `database/factories`,
  `database/seeders` (the seeder class is `PaymentModule\Database\Seeders\PaymentSeeder`).
- The `payments` table gained `gateway`, `reference_id`, `failure_reason` and `paid_at`;
  `user_id` is nullable and `amount` is an unsigned integer instead of `decimal(10,2)`.
  Existing installations must alter their table before upgrading.
- `status` is cast to the `PaymentStatus` enum.
- Routes: `payment/initiate` is POST only, `payment/callback` accepts GET and POST.
  Two routes were added: `GET payment` (form) and `GET payment/history`.
- The callback finds the payment by the gateway transaction id instead of `order_id`.
- Minimum supported versions: PHP 8.1, Laravel 10, `shetabit/multipay` 2.10.

### Added
- `PaymentService` (`create`, `pay`, `verify`, `findFromRequest`) with row locking and idempotent verification.
- `PaymentSucceeded` and `PaymentFailed` events.
- `config/payment-module.php`, publishable views, factory states, PHPUnit test suite.
- Docker setup (tests and a ready-to-run demo app) and GitHub Actions workflow.

### Fixed
- `order_id` was not mass-assignable because of stray whitespace in `$fillable`.
- `verify()` ignored the payment, so verification could never match the right transaction.
- The package controller no longer extends `App\Http\Controllers\Controller`.
- Routes are now registered inside the `web` middleware group.
- The service provider registers config, views and the service binding it previously lacked.
- `composer.json` declares Laravel auto-discovery.
