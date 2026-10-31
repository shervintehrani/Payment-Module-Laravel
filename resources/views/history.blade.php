@extends('payment-module::layout')

@section('title', 'Payment history')

@section('content')
    <h1>Payment history</h1>

    @if ($payments->isEmpty())
        <p>You have no payments yet.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payments as $payment)
                    <tr>
                        <td>{{ $payment->order_id }}</td>
                        <td>{{ number_format($payment->amount) }}</td>
                        <td><span class="badge badge-{{ $payment->status->value }}">{{ $payment->status->label() }}</span></td>
                        <td>{{ $payment->created_at->format('Y-m-d H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{ $payments->links() }}
    @endif
@endsection
