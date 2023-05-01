<?php

namespace AdminPayments\Gateways\Stripe;

use AdminPayments\Gateways\Exceptions\PaymentGateException;
use AdminPayments\Gateways\Exceptions\PaymentResponseException;
use AdminPayments\Gateways\PaymentGateway;
use Exception;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;
use Store;

class StripePayment extends PaymentGateway
{
    public $client;

    public function __construct($options = null)
    {
        parent::__construct(
            array_merge($options ?: [], config('stripe', []))
        );

        if ( !$this->getOption('api_key') ) {
            abort(500, 'Stripe configuration does not exists');
        }

        $this->client = new StripeClient(
            $this->getOption('api_key')
        );
    }

    protected function getPaymentObject()
    {
        $order = $this->getOrder();

        $data = [
            'mode' => 'payment',
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => Store::getCurrency()->code,
                        'unit_amount' => round($order->price_vat * 100),
                        'product_data' => [
                            'name' => 'Order n. '.$order->number,
                            'description' => $order->items->map(function($item){
                                return $item->quantity.'x - '.$item->getProductName();
                            })->join('... '),
                        ],
                    ],
                    'quantity' => 1,
                ],
            ],
            'success_url' => $this->getResponseUrl('status'),
            'cancel_url' => $this->getResponseUrl('status'),
            'payment_intent_data' => [
                'metadata' => [
                    'order_number' => $order->number,
                ],
            ],
        ];

        //If stripe customer exists, then assign payment under this customer. (Support for saved payment methods)
        if ( client() && $stripeCustomerId = client()->stripe_customer_id ){
            $data['customer'] = $stripeCustomerId;
        } else {
            $data['customer_email'] = $order->email;
        }

        if ( $types = $this->getOption('payment_method_types') ){
            $data['payment_method_types'] = array_wrap($types);
        }

        return $data;
    }

    public function getPaymentResponse()
    {
        $data = $this->getPaymentObject();

        try {
            $session = $this->client->checkout->sessions->create($data);

            $this->setPaymentId(
                $session->id,
                [
                    'data' => [
                        'intent_id' => $session->payment_intent,
                    ]
                ]
            );

            return $session->url;
        } catch (InvalidRequestException $e){
            throw new PaymentGateException($e->getRequestId(), null, $e->getHttpBody());
        } catch (Exception $e){
            throw new PaymentGateException($e->getMessage());
        }

        return $response;
    }

    public function isPaid($id = null)
    {
        $session = $this->client->checkout->sessions->retrieve($id);

        if ( $session->status == 'complete' ){
            return true;
        }

        throw new PaymentResponseException(
            'Payment is not paid.', null, $session
        );
    }
}

?>