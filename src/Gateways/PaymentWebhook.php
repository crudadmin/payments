<?php

namespace AdminPayments\Gateways;

use Admin;
use AdminPayments\Events\WebhookEvent;
use Admin\Core\Contracts\DataStore;
use Log;

class PaymentWebhook
{
    use DataStore;

    public function log()
    {
        return Log::channel('webhooks');
    }

    public function event($name, $event, $payment)
    {
        event(new WebhookEvent($name, $event, $payment, $this));
    }

    public function logEvent($event)
    {
        $this->log()->info($event);

        return $this;
    }

    public function getPayment($id)
    {
        return $this->cache($id, function() use ($id) {
            if ( !($payment = Admin::getModel('Payment')->where('payment_id', $id)->first()) ){
                $this->log()->error(class_basename(static::class).': payment not found: '.$id);

                return;
            }

            return $payment;
        });
    }
}

?>