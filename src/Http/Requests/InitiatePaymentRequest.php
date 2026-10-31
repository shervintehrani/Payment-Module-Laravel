<?php

declare(strict_types=1);

namespace PaymentModule\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload of POST {prefix}/initiate.
 */
class InitiatePaymentRequest extends FormRequest
{
    /** Access is controlled by the route middleware (see "auth_middleware"). */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:' . (int) config('payment-module.min_amount', 1000)],
            'gateway' => ['nullable', 'string', Rule::in(array_keys(config('payment-module.gateways', [])))],
        ];
    }
}
