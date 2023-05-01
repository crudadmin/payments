<?php

return [
    /**
     * Payment methods settings
     */
    'payment_methods' => [
        /*
         * Available payment methods
         */
        'providers' => [
            1 => AdminPayments\Contracts\Payments\GopayPayment::class,
        ],
    ],
];