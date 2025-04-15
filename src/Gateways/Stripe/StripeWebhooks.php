<?php

namespace AdminPayments\Gateways\Stripe;

use AdminPayments\Gateways\PaymentWebhook;
use Exception;
use Stripe\Stripe;

class StripeWebhooks extends PaymentWebhook
{
    public function setApiKey()
    {
        Stripe::setApiKey(config('stripe.api_key'));
    }

    public function getWebhookEvent()
    {
        $this->setApiKey();

        $endpointSecret = config('stripe.webhooks.secret') ?: config('stripe.webhook_secret');

        $payload = @file_get_contents('php://input');
        $event = null;

        if ( !$payload ){
            abort(501, 'No payload received.');
        }

        try {
            $event = \Stripe\Event::constructFrom(json_decode($payload, true));
        } catch(\UnexpectedValueException $e) {
            throw new Exception('Webhook error while parsing basic request.');
        } catch (\Throwable $e){
            throw new Exception($e);
        }

        if ($endpointSecret && config('stripe.webhooks.testing', false) == false ) {
            // Only verify the event if there is an endpoint secret defined
            // Otherwise use the basic decoded event
            $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'];
            try {
                $event = \Stripe\Webhook::constructEvent(
                    $payload, $sigHeader, $endpointSecret
                );
            } catch(\Stripe\Exception\SignatureVerificationException $e) {
                // Invalid signature
                throw new Exception('Webhook error while validating signature.');
            }
        }

        return $event;
    }

    public function onWebhookEvent($event)
    {
        $whitelistedEvents = config('stripe.webhooks.listen', [
            'checkout.session.completed',
        ]);

        //Check enabled webhooks
        if ( !in_array($event->type, $whitelistedEvents) ) {
            return;
        }

        $object = $event->data->object;

        $payment = $this->getPayment($object->id);

        if ( $payment && $payment->getPaymentProvider()?->canPassWebhook($event->type) ){
            return $payment->onWebhookEvent($event->type);
        }

        $this->event($event->type, $event, $payment);
    }
}

?>