<?php

namespace AdminPayments\Gateways\Paypal;

use AdminPayments\Gateways\PaymentWebhook;
use Exception;
use Illuminate\Support\Facades\Log;
use PaymentService;

class PaypalWebhooks extends PaymentWebhook
{
    private function getPaymentId($body)
    {
        return $body['resource']['supplementary_data']['related_ids']['order_id'] ?? $body['resource']['id'] ?? $body['token'] ?? null;
    }

    public function getWebhookEvent()
    {
        $body = request()->all();

        PaypalWebhookVerificator::$initialized = true;

        //Verify request
        if ( (new PaypalWebhookVerificator())->verify(request()->headers) === false ){
            //Disable verification for now.
            throw new Exception('Body request is not verified.');
        }

        if ( !$this->getPaymentId($body) ){
            throw new Exception('Payment id is missing.');
        }

        return $body;
    }

    public function onWebhookEvent($body)
    {
        $paymentId = $this->getPaymentId($body);

        $payment = $this->getPayment($paymentId);

        if ( !($order = $payment->order) ){
            throw new Exception('Order could not be found');
        }

        if ( isset($body['event_type']) ){
            $order->logReport('info', $body['event_type'], $body['event_type'].' - '.($body['summary'] ?? ''), ($body['resource'] ?? null));
        }

        if ( config('logging.channels.paypal_webhooks') ) {
            Log::channel('paypal_webhooks')->info($body);
        }

        //When order is approved, we need initialize capture of order.
        if ( isset($body['event_type']) && in_array($body['event_type'], ['CHECKOUT.ORDER.APPROVED']) ) {
            return $payment->isPaymentPaid();
        }
    }
}
