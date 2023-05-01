<?php

namespace AdminPayments\Gateways\Contracts\Exceptions;

class PaymentResponseException extends PaymentException
{
    public $code = 'PAYMENT_UNVERIFIED';
}