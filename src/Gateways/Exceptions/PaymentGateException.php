<?php

namespace AdminPayments\Gateways\Exceptions;

class PaymentGateException extends PaymentException
{
    public $code = 'PAYMENT_ERROR';
}