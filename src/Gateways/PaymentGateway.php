<?php

namespace AdminPayments\Gateways;

use AdminPayments\Contracts\ConfigProvider;
use AdminPayments\Contracts\Exceptions\PaymentResponseException;
use AdminPayments\Models\Payments\PaymentsLog;
use PaymentService;
use Exception;

class PaymentGateway extends ConfigProvider
{
    /**
     * Payment mode / payment or subscription
     *
     * @var string
     */
    protected $mode = 'payment';

    /**
     * payment
     *
     * @var \AdminPayments\Models\Payments\Payment
     */
    private $payment;

    /**
     * response
     *
     * @var mixed
     */
    private $response;

    /**
     * Which webhooks are supported by this provider
     *
     * @var array
     */
    protected $webhooks = [];

    /**
     * Get currently logged user
     *
     * @return void
     */
    public function user()
    {
        return client();
    }

    /**
     * Check if payment is subscription
     *
     * @return void
     */
    public function isSubscription()
    {
        return $this->mode === 'subscription';
    }

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
        $payment = $this->getPayment();
        $payment->setPaymentData($data);
        $payment->payment_id = $paymentId;
        $payment->save();

        return $this;
    }

    public function setPaymentData($data = [])
    {
        $this->getPayment()->setPaymentData($data)->save();

        return $this;
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
        ]).'?origin='.urldecode(request()->headers->get('origin') ?: '');
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

    public function getSuccessResponse()
    {
        return redirect(PaymentService::onPaymentSuccess());
    }

    public function getErrorResponse(PaymentsLog $log)
    {
        return redirect(PaymentService::onPaymentError($log->code));
    }

    public function getNotificationResponse()
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
     * Determine whatever given webhoon can be passed into provider events
     *
     * @param  string  $name
     *
     * @return  bool
     */
    public function canPassWebhook($name)
    {
        return count($this->webhooks) == 0 || in_array($name, $this->webhooks);
    }

    /**
     * On payment paid successfully
     */
    public function onPaid($type)
    {
        $this->getPayment()->setPaymentPaid($type);
    }

    /**
     * On paid payment validation check
     *
     * @param  string  $paymentId
     * @param  string|optional  $webhookName
     */
    public function onCheck($paymentId, $webhookName)
    {
        $this->getPayment()->setPaymentCheck($webhookName);
    }

    /**
     * On subscription payment
     *
     * @param  string  $paymentId
     */
    public function onSubscription($subscriptionId)
    {
        $subscription = $this->getSubscription($subscriptionId);

        $this->getPayment()->setSubscribed($subscription);
    }

    /**
     * Generate payment number from order, etc.
     *
     * @return  string
     */
    public function getPaymentNumber()
    {
        return $this->getOrder()?->number ?: $this->payment->getKey();
    }

    /**
     * Returns payment description
     *
     * @return  string
     */
    public function getPaymentTitle()
    {
        return $this->getOrder()->getPaymentTitle(
            $this->getPaymentNumber()
        );
    }
}

?>