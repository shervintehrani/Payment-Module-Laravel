<?php

declare(strict_types=1);

namespace PaymentModule\Enums;

/**
 * Lifecycle of a payment.
 *
 *   Pending ──verify OK──▶ Success
 *      └─────verify KO / purchase error──▶ Failed
 *
 * Success and Failed are final: a payment never leaves them.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';

    /** Human readable label, handy for views and API responses. */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Success => 'Successful',
            self::Failed => 'Failed',
        };
    }

    /** Whether the payment can still change state. */
    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
