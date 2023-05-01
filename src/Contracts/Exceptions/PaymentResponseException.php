<?php

namespace AdminPayments\Contracts\Exceptions;

class PaymentResponseException extends PaymentException
{
    public $code = 'PAYMENT_UNVERIFIED';
}