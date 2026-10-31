<?php

declare(strict_types=1);

namespace PaymentModule\Exceptions;

use PaymentModule\Models\Payment;
use RuntimeException;
use Throwable;

/**
 * The only exception type the package throws on purpose.
 *
 * Gateway specific exceptions are wrapped in it, so application code needs a
 * single catch block and never depends on Multipay internals.
 */
class PaymentException extends RuntimeException
{
    /** The gateway rejected the request or could not be reached. */
    public static function gatewayFailure(Throwable $previous): self
    {
        return new self('The payment gateway request failed: ' . $previous->getMessage(), 0, $previous);
    }

    /** The requested gateway is not present in config/payment-module.php. */
    public static function unknownGateway(string $gateway): self
    {
        return new self("Payment gateway [{$gateway}] is not configured.");
    }

    /** Only pending payments can be sent to a gateway. */
    public static function notPayable(Payment $payment): self
    {
        return new self("Payment [{$payment->order_id}] is not pending and cannot be paid.");
    }

    /** The callback route is needed to tell the gateway where to return. */
    public static function missingCallbackUrl(): self
    {
        return new self(
            'No callback URL available: enable the package routes or set payment-module.callback_url.'
        );
    }
}
