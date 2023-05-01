<?php

return [
    /**
     * Payment methods settings
     */
    'payment_methods' => [
        'enabled' => true,
    ],

    /*
     * Available payment methods
     */
    'providers' => [
        // 1 => AdminPayments\Gateways\GopayPayment::class,
    ],

    'invoices' => [
        'enabled' => true,
    ],

    'notifications' => [
        'paid' => true,
    ],

    'logger_models' => [
        // /App\Order::class,
    ],
];