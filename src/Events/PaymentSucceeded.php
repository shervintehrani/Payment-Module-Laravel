<?php

declare(strict_types=1);

namespace PaymentModule\Events;

use Illuminate\Foundation\Events\Dispatchable;
use PaymentModule\Models\Payment;

/**
 * Dispatched once, right after a payment has been verified successfully.
 *
 * Listen to it to fulfil the order, send a receipt, and so on.
 */
class PaymentSucceeded
{
    use Dispatchable;

    public function __construct(public readonly Payment $payment)
    {
    }
}
