<?php

namespace AdminPayments\Contracts\Exceptions;

class PaymentGateException extends PaymentException
{
    public $code = 'PAYMENT_ERROR';
}