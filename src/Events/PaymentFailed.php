<?php

declare(strict_types=1);

namespace PaymentModule\Events;

use Illuminate\Foundation\Events\Dispatchable;
use PaymentModule\Models\Payment;

/**
 * Dispatched once, right after a payment has been marked as failed
 * (purchase error, cancelled by the customer, or rejected verification).
 */
class PaymentFailed
{
    use Dispatchable;

    public function __construct(public readonly Payment $payment)
    {
    }
}
