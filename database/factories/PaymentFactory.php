<?php

declare(strict_types=1);

namespace PaymentModule\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use PaymentModule\Enums\PaymentStatus;
use PaymentModule\Models\Payment;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => 'ORD-' . Str::upper(Str::random(16)),
            'user_id' => null,
            'gateway' => config('payment-module.default_gateway', 'zarinpal'),
            'amount' => $this->faker->numberBetween(10_000, 500_000),
            'status' => PaymentStatus::Pending,
            'transaction_id' => null,
            'reference_id' => null,
            'failure_reason' => null,
            'paid_at' => null,
        ];
    }

    /** A payment that was sent to the gateway and waits for the callback. */
    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Pending,
            'transaction_id' => 'AUTH-' . Str::upper(Str::random(12)),
        ]);
    }

    /** A verified payment. */
    public function successful(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Success,
            'transaction_id' => 'AUTH-' . Str::upper(Str::random(12)),
            'reference_id' => (string) $this->faker->numberBetween(100_000_000, 999_999_999),
            'paid_at' => now(),
        ]);
    }

    /** A payment the gateway rejected or the customer cancelled. */
    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Failed,
            'transaction_id' => null,
            'failure_reason' => 'Cancelled by the customer.',
        ]);
    }

    /** Attach the payment to a user id. */
    public function forUser(int $userId): static
    {
        return $this->state(fn () => ['user_id' => $userId]);
    }
}
