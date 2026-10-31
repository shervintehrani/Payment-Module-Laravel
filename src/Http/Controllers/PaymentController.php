<?php

declare(strict_types=1);

namespace PaymentModule\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use PaymentModule\Exceptions\PaymentException;
use PaymentModule\Http\Requests\InitiatePaymentRequest;
use PaymentModule\Models\Payment;
use PaymentModule\Services\PaymentService;

/**
 * Thin HTTP layer: all the business logic lives in PaymentService.
 *
 * Extends Illuminate\Routing\Controller instead of App\Http\Controllers\Controller
 * so the package does not depend on the host application's class.
 */
class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments)
    {
    }

    /** GET {prefix}: the payment form. */
    public function form(): View
    {
        return view('payment-module::initiate', [
            'minAmount' => (int) config('payment-module.min_amount', 1000),
            'gateways' => $this->payments->gateways(),
            'defaultGateway' => config('payment-module.default_gateway'),
        ]);
    }

    /** POST {prefix}/initiate: create the payment and send the customer to the bank. */
    public function initiate(InitiatePaymentRequest $request): RedirectResponse|Response|JsonResponse
    {
        $payment = $this->payments->create(
            amount: (int) $request->validated('amount'),
            userId: $request->user()?->getAuthIdentifier(),
            gateway: $request->validated('gateway'),
        );

        try {
            $form = $this->payments->pay($payment);
        } catch (PaymentException $e) {
            report($e);

            // Never leak gateway error details to the customer.
            $message = 'The payment could not be started. Please try again later.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 502)
                : back()->withInput()->withErrors(['payment' => $message]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'order_id' => $payment->order_id,
                'redirect' => [
                    'url' => $form->getAction(),
                    'method' => strtoupper($form->getMethod()),
                    'inputs' => $form->getInputs(),
                ],
            ]);
        }

        // GET gateways (e.g. Zarinpal) need a plain redirect; POST gateways need
        // the auto-submitting HTML form that Multipay renders.
        return strtoupper($form->getMethod()) === 'GET'
            ? redirect()->away($form->getAction())
            : response($form->render());
    }

    /**
     * GET|POST {prefix}/callback: where the gateway sends the customer back.
     *
     * Intentionally not behind "auth" and exempt from CSRF: the request comes
     * from the bank, and the payment is identified by its transaction id.
     */
    public function callback(Request $request): View|JsonResponse
    {
        $payment = $this->payments->findFromRequest($request);

        abort_if($payment === null, 404, 'Payment not found.');

        try {
            $payment = $this->payments->verify($payment);
        } catch (PaymentException $e) {
            report($e);

            $message = 'We could not confirm your payment yet. Please refresh this page in a moment.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 503)
                : view('payment-module::result', ['payment' => $payment, 'error' => $message]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'order_id' => $payment->order_id,
                'status' => $payment->status->value,
                'reference_id' => $payment->reference_id,
            ]);
        }

        return view('payment-module::result', ['payment' => $payment, 'error' => null]);
    }

    /** GET {prefix}/history: the signed-in user's own payments. */
    public function history(Request $request): View
    {
        $payments = Payment::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->latest()
            ->paginate(15);

        return view('payment-module::history', ['payments' => $payments]);
    }
}
