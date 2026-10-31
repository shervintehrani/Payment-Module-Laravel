<?php

declare(strict_types=1);

namespace PaymentModule\Database\Seeders;

use Illuminate\Database\Seeder;
use PaymentModule\Models\Payment;

/**
 * Creates 15 sample payments (5 pending, 5 successful, 5 failed).
 *
 * Requires fakerphp/faker, which Laravel ships as a dev dependency:
 *
 *     php artisan db:seed --class="PaymentModule\Database\Seeders\PaymentSeeder"
 */
class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        Payment::factory()->count(5)->pending()->create();
        Payment::factory()->count(5)->successful()->create();
        Payment::factory()->count(5)->failed()->create();
    }
}
