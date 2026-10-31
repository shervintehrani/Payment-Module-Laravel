@extends('payment-module::layout')

@section('title', 'Payment result')

@section('content')
    @if ($error)
        <h1>Payment status unknown</h1>
        <div class="alert" role="alert"><p>{{ $error }}</p></div>
    @elseif ($payment->isSuccessful())
        <h1>Payment successful</h1>
        <p>Thank you! Your payment has been confirmed.</p>
    @else
        <h1>Payment failed</h1>
        <p>Your payment was not completed. No money has been charged for this attempt.</p>
    @endif

    <dl>
        <dt>Order</dt>
        <dd>{{ $payment->order_id }}</dd>

        <dt>Amount</dt>
        <dd>{{ number_format($payment->amount) }}</dd>

        <dt>Status</dt>
        <dd><span class="badge badge-{{ $payment->status->value }}">{{ $payment->status->label() }}</span></dd>

        @if ($payment->reference_id)
            <dt>Reference</dt>
            <dd>{{ $payment->reference_id }}</dd>
        @endif
    </dl>

    @if (Route::has('payment.form'))
        <a class="button" href="{{ route('payment.form') }}">Back</a>
    @endif
@endsection
