<?php

return [
    /**
     * Payment methods settings
     */
    'payment_methods' => [
        'enabled' => true,

        /*
         * Available payment methods
         */
        'providers' => [
            // 1 => [
            //     'provider' => AdminPayments\Gateways\Gopay\GopayPayment::class,
            //     'options' => [
            //         //..
            //     ],
            // ],
        ],
    ],

    'invoices' => [
        'enabled' => true,
    ],

    'notifications' => [
        'paid' => true,
    ],

    /**
     * You can override payment error messages according to code
     * Codes: PAYMENT_INITIALIZATION_ERROR, PAYMENT_ERROR, PAYMENT_UNVERIFIED, PAYMENT_PAID, INVOICE_ERROR
     */
    'error_codes' => [
        // 'PAYMENT_ERROR' => _('...'),
        // 'PAYMENT_PAID' => _('...'),
        // ..
    ],

    'testing' => env('PAYMENTS_TESTING', false),
];