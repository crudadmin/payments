<?php

namespace AdminPayments\Gateways\Contracts\Exceptions;

class PaymentGateException extends PaymentException
{
    public $code = 'PAYMENT_ERROR';
}