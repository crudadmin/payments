<?php

namespace AdminPayments\Contracts\Payments\Exceptions;

use AdminPayments\Contracts\Order\Exceptions\OrderException;

class PaymentResponseException extends OrderException
{
    public $code = 'PAYMENT_UNVERIFIED';
}