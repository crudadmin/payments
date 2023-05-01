<?php

namespace AdminPayments\Gateways\Exceptions;

use AdminPayments\Contracts\Order\Exceptions\OrderException;

class PaymentGateException extends OrderException
{
    public $code = 'PAYMENT_ERROR';
}