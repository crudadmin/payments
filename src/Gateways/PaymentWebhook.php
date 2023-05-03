<?php

namespace AdminPayments\Gateways;

use Log;
use Admin;

class PaymentWebhook
{
    public function getPayment($id)
    {
        if ( !($payment = Admin::getModel('Payment')->where('payment_id', $id)->first()) ){
            Log::channel('payments')->error('Payment could not be found in '.class_basename(static::class).' webhook: '.$id);

            return;
        }

        return $payment;
    }
}

?>