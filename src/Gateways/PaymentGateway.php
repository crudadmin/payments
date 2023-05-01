<?php

namespace AdminPayments\Gateways;

use AdminPayments\Contracts\ConfigProvider;
use AdminPayments\Gateways\Exceptions\PaymentResponseException;

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

    /*
     * Get order payment hash
     */
    public function getOrderHash($type = null)
    {
        return $this->getOrder()->makePaymentHash($type);
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
        return action('\AdminPayments\Controllers\PaymentController@paymentStatus', [
            $this->getPayment()->getKey(),
            $type,
            $this->getOrderHash($type),
        ]);
    }

    public function getPostPaymentUrl($paymentResponse)
    {
        $type = 'postpayment';

        return action('\AdminPayments\Controllers\PaymentController@postPayment', [
            $this->getOrder()->getKey(),
            $this->getOrderHash($type),
        ]);
    }

    public function getNotificationResponse($paymentId)
    {
        return ['success' => true];
    }

    /**
     * On payment paid successfully
     */
    // public function onPaid(){}
}

?>