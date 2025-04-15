<?php

namespace AdminPayments\Gateways\Stripe;

use AdminPayments\Contracts\Exceptions\PaymentGateException;
use AdminPayments\Contracts\Exceptions\PaymentResponseException;
use AdminPayments\Gateways\PaymentGateway;
use Exception;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;
use Store;

class StripeIntentPayment extends StripePayment
{
    protected $webhooks = [
        'payment_intent.succeeded'
    ];

    protected function getPaymentObject()
    {
        return array_filter([
            'currency' => Store::getCurrency()->code,
            'amount' => round($this->getPayment()->price * 100),
            'metadata' => [
                'order_number' => $this->getOrder()->number,
            ],
            'customer' => $user = $this->user() ? $user->stripe_customer_id : null,
            'payment_method_types' => $this->getOption('payment_method_types'),
        ]);
    }

    public function getPaymentResponse()
    {
        $data = $this->getPaymentObject();

        try {
            $intent = $this->client->paymentIntents->create($data);

            $this->setPaymentId($intent->id);

            return [
                'secret' => $intent->client_secret,
                'return_url' => $this->getResponseUrl('status'),
            ];
        } catch (InvalidRequestException $e){
            throw new PaymentGateException($e->getRequestId(), null, $e->getHttpBody());
        } catch (Exception $e){
            throw new PaymentGateException($e->getMessage());
        }
    }

    public function isPaid($id = null)
    {
        $intent = $this->client->paymentIntents->retrieve($id);

        if ( $intent->status == 'succeeded' ){
            return true;
        }

        throw new PaymentResponseException(
            'Payment is not paid.', null, $intent
        );
    }

    /**
     * Returns payment url from payment response
     *
     * @param  mixed  $paymentResponse
     *
     * @return  string|null
     */
    public function getPaymentUrl($paymentResponse)
    {
        //..
    }

    /**
     * Returns additional payment data
     *
     * @param  mixed  $paymentResponse
     *
     * @return  array
     */
    public function getPaymentData($paymentResponse)
    {
        return array_merge($paymentResponse, []);
    }

}

?>