<?php

/*
|--------------------------------------------------------------------------
| Payment Module configuration
|--------------------------------------------------------------------------
|
| Publish this file with:
|
|     php artisan vendor:publish --tag=payment-module-config
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Default gateway
    |--------------------------------------------------------------------------
    |
    | The key of one of the entries in the "gateways" array below. It is used
    | whenever a payment is created without an explicit gateway.
    |
    */
    'default_gateway' => env('PAYMENT_MODULE_GATEWAY', 'zarinpal'),

    /*
    |--------------------------------------------------------------------------
    | Database table
    |--------------------------------------------------------------------------
    |
    | Name of the table that stores the payments. Change it BEFORE running the
    | package migration for the first time.
    |
    */
    'table' => 'payments',

    /*
    |--------------------------------------------------------------------------
    | Minimum amount
    |--------------------------------------------------------------------------
    |
    | Smallest amount accepted by the built-in "initiate" endpoint, expressed in
    | the currency configured on the gateway (Toman or Rial).
    |
    */
    'min_amount' => (int) env('PAYMENT_MODULE_MIN_AMOUNT', 1000),

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | enabled          Set to false to register your own routes and use the
    |                  PaymentService directly.
    | prefix           URL prefix of every package route.
    | middleware       Applied to every package route. "web" is required because
    |                  the package relies on sessions and validation redirects.
    | auth_middleware  Applied to the payment form and the initiate endpoint.
    |                  The callback endpoint is never protected: the gateway
    |                  redirects the customer's browser to it, and the payment
    |                  is identified by the gateway transaction id instead.
    |                  The history endpoint always requires "auth".
    |
    */
    'routes' => [
        'enabled' => true,
        'prefix' => env('PAYMENT_MODULE_ROUTE_PREFIX', 'payment'),
        'middleware' => ['web'],
        'auth_middleware' => env('PAYMENT_MODULE_REQUIRE_AUTH', true) ? ['auth'] : [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Callback URL
    |--------------------------------------------------------------------------
    |
    | Absolute URL the gateway sends the customer back to. Leave it null to use
    | the package's own "payment.callback" route. Set it when the application
    | sits behind a proxy and route() generates the wrong host or scheme.
    |
    */
    'callback_url' => env('PAYMENT_MODULE_CALLBACK_URL'),

    /*
    |--------------------------------------------------------------------------
    | Callback transaction id keys
    |--------------------------------------------------------------------------
    |
    | Gateways return the transaction id under different request parameters.
    | The callback endpoint checks these keys in order and uses the first one
    | that is present. Add the parameter name of any gateway you configure.
    |
    */
    'callback_transaction_keys' => [
        'Authority',      // Zarinpal
        'authority',
        'transactionId',  // Multipay "local" test gateway
        'transaction_id',
        'track_id',
        'trackId',
        'token',
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateways (Shetabit Multipay drivers)
    |--------------------------------------------------------------------------
    |
    | Every key is a Multipay driver name. Copy the settings of any other driver
    | from the Multipay documentation to enable it, then add the parameter it
    | calls back with to "callback_transaction_keys".
    |
    | "currency" tells the driver which unit your amounts are stored in:
    | "T" = Toman, "R" = Rial.
    |
    */
    'gateways' => [

        'zarinpal' => [
            // Normal API
            'apiPurchaseUrl' => 'https://api.zarinpal.com/pg/v4/payment/request.json',
            'apiPaymentUrl' => 'https://www.zarinpal.com/pg/StartPay/',
            'apiVerificationUrl' => 'https://api.zarinpal.com/pg/v4/payment/verify.json',

            // Sandbox API
            'sandboxApiPurchaseUrl' => 'https://sandbox.zarinpal.com/pg/v4/payment/request.json',
            'sandboxApiPaymentUrl' => 'https://sandbox.zarinpal.com/pg/StartPay/',
            'sandboxApiVerificationUrl' => 'https://sandbox.zarinpal.com/pg/v4/payment/verify.json',

            'mode' => env('ZARINPAL_MODE', 'normal'), // normal | sandbox
            'merchantId' => env('ZARINPAL_MERCHANT_ID', ''),
            'callbackUrl' => '', // filled in automatically, see "callback_url"
            'description' => env('ZARINPAL_DESCRIPTION', 'Online payment'),
            'currency' => env('ZARINPAL_CURRENCY', 'T'),
        ],

        // Offline fake gateway shipped with Multipay. Handy for local
        // development and demos: set PAYMENT_MODULE_GATEWAY=local.
        'local' => [
            'callbackUrl' => '', // filled in automatically, see "callback_url"
            'title' => 'Test payment gateway',
            'description' => 'This gateway only exists to test the payment flow.',
            'orderLabel' => 'Order number',
            'amountLabel' => 'Amount',
            'payButton' => 'Pay successfully',
            'cancelButton' => 'Cancel payment',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom driver map
    |--------------------------------------------------------------------------
    |
    | Register your own Multipay driver classes here as "name" => Class::class.
    | They are merged on top of the drivers that ship with Multipay.
    |
    */
    'map' => [],
];
