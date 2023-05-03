<?php

namespace AdminPayments\Gateways;

use Log;
use Admin;

class PaymentWebhook
{
    public function log()
    {
        return Log::channel('webhooks');
    }

    public function logEvent($event)
    {
        $this->log()->info($event);

        return $this;
    }

    public function getPayment($id)
    {
        if ( !($payment = Admin::getModel('Payment')->where('payment_id', $id)->first()) ){
            $this->log()->error(class_basename(static::class).': payment not found: '.$id);

            return;
        }

        return $payment;
    }
}

?>