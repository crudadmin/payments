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
];