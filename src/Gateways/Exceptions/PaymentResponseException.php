<?php

namespace AdminPayments\Gateways\Exceptions;

class PaymentResponseException extends PaymentException
{
    public $code = 'PAYMENT_UNVERIFIED';
}