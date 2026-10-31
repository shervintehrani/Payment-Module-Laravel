@extends('payment-module::layout')

@section('title', 'Make a payment')

@section('content')
    <h1>Make a payment</h1>

    @if ($errors->any())
        <div class="alert" role="alert">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('payment.initiate') }}">
        @csrf

        <label for="amount">Amount</label>
        <input id="amount" name="amount" type="number" inputmode="numeric"
               min="{{ $minAmount }}" step="1" value="{{ old('amount') }}" required>

        @if (count($gateways) > 1)
            <label for="gateway">Gateway</label>
            <select id="gateway" name="gateway">
                @foreach ($gateways as $gateway)
                    <option value="{{ $gateway }}" @selected(old('gateway', $defaultGateway) === $gateway)>
                        {{ ucfirst($gateway) }}
                    </option>
                @endforeach
            </select>
        @endif

        <button type="submit">Pay</button>
    </form>
@endsection
