<?php

namespace AdminPayments\Gateways;

use Admin;
use Exception;
use PaymentService;

class PaymentWebhook
{
    public function getPayment($id)
    {
        if ( !($payment = Admin::getModel('Payment')->where('payment_id', $id)->first()) ){
            throw new Exception('Payment could not be found: '.$id);
        }

        return $payment;
    }
}

?>