<?php

declare(strict_types=1);

namespace PaymentModule\Services;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PaymentModule\Enums\PaymentStatus;
use PaymentModule\Events\PaymentFailed;
use PaymentModule\Events\PaymentSucceeded;
use PaymentModule\Exceptions\PaymentException;
use PaymentModule\Models\Payment;
use Shetabit\Multipay\Exceptions\InvalidPaymentException;
use Shetabit\Multipay\Invoice;
use Shetabit\Multipay\Payment as Multipay;
use Shetabit\Multipay\RedirectionForm;
use Throwable;

/**
 * Orchestrates the payment life cycle:
 *
 *   create()  ─▶  pay()  ─▶  (customer pays at the bank)  ─▶  verify()
 *
 * The class is the only place that talks to Shetabit Multipay, so gateways can
 * be swapped or mocked without touching controllers.
 */
class PaymentService
{
    /**
     * Builds a FRESH Multipay instance on every call. Multipay keeps mutable
     * state (selected driver, invoice, settings), so sharing one instance
     * between requests would leak data, for example under Laravel Octane.
     *
     * @var Closure(): Multipay
     */
    private Closure $gatewayFactory;

    /**
     * @param array<string, mixed> $config         The "payment-module" configuration array.
     * @param Closure|null         $gatewayFactory Override the Multipay factory (used by tests).
     */
    public function __construct(private readonly array $config, ?Closure $gatewayFactory = null)
    {
        $this->gatewayFactory = $gatewayFactory ?? fn (): Multipay => $this->makeMultipay();
    }

    /* ------------------------------------------------------------------ *
     | Public API
     * ------------------------------------------------------------------ */

    /**
     * Store a new pending payment.
     *
     * @throws PaymentException When the gateway is not configured.
     */
    public function create(int $amount, ?int $userId = null, ?string $gateway = null): Payment
    {
        $gateway ??= $this->config['default_gateway'];

        if (! array_key_exists($gateway, $this->config['gateways'] ?? [])) {
            throw PaymentException::unknownGateway($gateway);
        }

        return Payment::create([
            // ULIDs are unique and sortable, which makes support lookups easy.
            'order_id' => 'ORD-' . Str::upper((string) Str::ulid()),
            'user_id' => $userId,
            'gateway' => $gateway,
            'amount' => $amount,
            'status' => PaymentStatus::Pending,
        ]);
    }

    /**
     * Register the payment with the gateway and get the redirection to it.
     *
     * The gateway transaction id is stored on the payment, so the callback
     * request can be matched to it later.
     *
     * @throws PaymentException
     */
    public function pay(Payment $payment): RedirectionForm
    {
        if (! $payment->isPending()) {
            throw PaymentException::notPayable($payment);
        }

        try {
            $invoice = new Invoice();
            $invoice->amount($payment->amount);
            $invoice->detail('orderId', $payment->order_id);

            return $this->gateway($payment->gateway)
                ->purchase($invoice, function ($driver, $transactionId) use ($payment): void {
                    $payment->forceFill(['transaction_id' => (string) $transactionId])->save();
                })
                ->pay();
        } catch (PaymentException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->fail($payment, $e->getMessage());

            throw PaymentException::gatewayFailure($e);
        }
    }

    /**
     * Ask the gateway whether the customer really paid, then settle the payment.
     *
     * - Idempotent: calling it again for a settled payment returns it untouched.
     * - Race safe: the row is locked, so two simultaneous callbacks cannot
     *   verify (and fulfil) the same payment twice.
     * - A rejected payment is marked as failed. A technical problem (timeout,
     *   gateway down) leaves it pending and throws, so the callback can be
     *   retried instead of losing a payment that may have been successful.
     *
     * @throws PaymentException On technical gateway errors.
     */
    public function verify(Payment $payment): Payment
    {
        $outcome = null;

        $settled = DB::transaction(function () use ($payment, &$outcome): Payment {
            /** @var Payment $locked */
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if (! $locked->isPending()) {
                return $locked;
            }

            // Without a transaction id the purchase never completed.
            if ($locked->transaction_id === null) {
                $locked->markAsFailed('The payment was never registered with the gateway.');
                $outcome = PaymentStatus::Failed;

                return $locked;
            }

            try {
                $receipt = $this->gateway($locked->gateway)
                    ->amount($locked->amount)
                    ->transactionId($locked->transaction_id)
                    ->verify();
            } catch (InvalidPaymentException $e) {
                // The bank says the payment is invalid or was cancelled.
                $locked->markAsFailed($e->getMessage());
                $outcome = PaymentStatus::Failed;

                return $locked;
            } catch (Throwable $e) {
                throw PaymentException::gatewayFailure($e);
            }

            $locked->markAsPaid($receipt->getReferenceId());
            $outcome = PaymentStatus::Success;

            return $locked;
        });

        // Events are dispatched after the transaction has been committed.
        match ($outcome) {
            PaymentStatus::Success => PaymentSucceeded::dispatch($settled),
            PaymentStatus::Failed => PaymentFailed::dispatch($settled),
            default => null,
        };

        return $settled;
    }

    /**
     * Find the payment a callback request refers to.
     *
     * Gateways identify the payment by the transaction id they issued, but
     * under different parameter names; see "callback_transaction_keys".
     */
    public function findFromRequest(Request $request): ?Payment
    {
        foreach ($this->config['callback_transaction_keys'] ?? [] as $key) {
            $value = $request->input($key);

            if (is_string($value) && $value !== '') {
                return Payment::query()->where('transaction_id', $value)->first();
            }
        }

        return null;
    }

    /** Names of the configured gateways. */
    public function gateways(): array
    {
        return array_keys($this->config['gateways'] ?? []);
    }

    /* ------------------------------------------------------------------ *
     | Internals
     * ------------------------------------------------------------------ */

    /** Select the driver and point it to our callback route. */
    private function gateway(string $name): Multipay
    {
        if (! array_key_exists($name, $this->config['gateways'] ?? [])) {
            throw PaymentException::unknownGateway($name);
        }

        return ($this->gatewayFactory)()
            ->via($name)
            ->callbackUrl($this->callbackUrl());
    }

    private function callbackUrl(): string
    {
        if (! empty($this->config['callback_url'])) {
            return $this->config['callback_url'];
        }

        if (! Route::has('payment.callback')) {
            throw PaymentException::missingCallbackUrl();
        }

        return route('payment.callback');
    }

    /**
     * Build Multipay from this package's configuration. Multipay needs the
     * driver "map" too, so the ones that ship with it are used as the base.
     */
    private function makeMultipay(): Multipay
    {
        $defaults = require Multipay::getDefaultConfigPath();

        return new Multipay([
            'default' => $this->config['default_gateway'],
            'drivers' => $this->config['gateways'],
            'map' => array_merge($defaults['map'] ?? [], $this->config['map'] ?? []),
        ]);
    }

    private function fail(Payment $payment, string $reason): void
    {
        $payment->markAsFailed($reason);
        PaymentFailed::dispatch($payment);
    }
}
