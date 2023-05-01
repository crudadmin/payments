<?php

namespace AdminPayments\Gateways;

use AdminPayments\Contracts\ConfigProvider;
use AdminPayments\Gateways\Contracts\Exceptions\PaymentResponseException;
use PaymentService;

class PaymentGateway extends ConfigProvider
{
    private $payment;

    private $response;

    public function getPayment()
    {
        return $this->payment;
    }

    public function setPayment($payment)
    {
        $this->payment = $payment;

        return $this;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function setResponse($response)
    {
        $this->response = $response;

        return $this;
    }

    /**
     * Set received payment id of created payment from provider
     *
     * @param  string|int  $paymentId
     */
    public function setPaymentId($paymentId, $data = [])
    {
        $this->getPayment()->update(array_merge([
            'payment_id' => $paymentId,
        ], $data));
    }

    public function setPaymentData($data = [])
    {
        $payment = $this->getPayment();

        $payment->update([
            'data' => array_merge($payment->data ?: [], $data)
        ]);
    }

    /**
     * Get created payment ID from provider.
     * It is more secure to set received payment id from provider, and then use this number from database.
     * Because if someone would change ?code or ?id parameter returned from payment, they make fake paid payment.
     */
    public function getPaymentId()
    {
        return $this->getPayment()->payment_id;
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
        return [
            'url' => $this->getPaymentUrl($paymentResponse),
        ];
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
        return $paymentResponse;
    }

    public function getResponseUrl($type)
    {
        $payment = $this->getPayment();

        return action('\AdminPayments\Controllers\PaymentController@paymentStatus', [
            $payment->getKey(),
            $type,
            $payment->getPaymentHash($type),
        ]);
    }

    public function getPostPaymentUrl($paymentResponse)
    {
        $order = $this->getOrder();

        return action('\AdminPayments\Controllers\PaymentController@postPayment', [
            $order->getTable(),
            $order->getKey(),
            $order->getPaymentHash('postpayment'),
        ]);
    }

    public function getNotificationResponse($paymentId)
    {
        return ['success' => true];
    }

    public function initialize()
    {
        $order = $this->getOrder();

        $paymentMethodId = $this->getIdentifier();

        $key = 'payments.'.$order->getTable().'.'.$order->getKey().'.'.$paymentMethodId.'.data';

        return $this->cache($key, function() use ($order, $paymentMethodId) {
            try {
                $payment = $order->makePayment($paymentMethodId);

                $this->setPayment($payment);

                $this->setResponse(
                    $this->getPaymentResponse()
                );

                return $this;
            } catch (Exception $e){
                $this->getOrder()->logException($e, function($log){
                    $log->code = 'PAYMENT_INITIALIZATION_ERROR';
                });

                if ( PaymentService::isDebug() ) {
                    throw $e;
                }
            }
        });
    }

    /**
     * On payment paid successfully
     */
    // public function onPaid(){}
}

?>