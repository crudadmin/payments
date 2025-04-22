<?php

namespace AdminPayments\Gateways\Stripe;

use Exception;
use Stripe\Exception\InvalidRequestException;
use AdminPayments\Contracts\Exceptions\PaymentGateException;
use AdminPayments\Gateways\Stripe\Concerns\HasStripeSubscription;

class StripeSubscription extends StripePayment
{
    use HasStripeSubscription;

    /**
     * Payment mode / payment or subscription
     *
     * @var string
     */
    protected $mode = 'subscription';

    /**
     * Which webhooks to listen to
     *
     * @var array
     */
    protected $webhooks = [
        'checkout.session.completed',
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.deleted',
    ];

    public function getPaymentResponse()
    {
        try {
            $data = $this->getPaymentObject();

            $session = $this->client->checkout->sessions->create($data);

            $this->setPaymentId($session->id);

            return $session->url;
        } catch (InvalidRequestException $e){
            throw new PaymentGateException($e->getRequestId(), null, $e->getHttpBody());
        } catch (Exception $e){
            throw new PaymentGateException($e->getMessage());
        }
    }

}
