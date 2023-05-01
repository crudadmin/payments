<?php

namespace AdminPayments\Contracts\Concerns;

use Admin;
use AdminPayments\Events\OrderPaid as OrderPaidEvent;
use AdminPayments\Gateways\GopayPayment;
use AdminPayments\Models\Payments\Payment;
use Carbon\Carbon;
use Exception;

trait HasPayments
{
    protected $paymentTypesConfigKey = 'adminpayments.payment_methods.providers';

    protected $onPaymentSuccessCallback = null;
    protected $onPaymentErrorCallback = null;

    public function setOnPaymentSuccess(callable $callback)
    {
        $this->onPaymentSuccessCallback = $callback;
    }

    public function setOnPaymentError(callable $callback)
    {
        $this->onPaymentErrorCallback = $callback;
    }

    public function onPaymentSuccess()
    {
        $order = $this->getOrder();

        if ( is_callable($callback = $this->onPaymentSuccessCallback) ) {
            return $callback($order);
        }
    }

    /**
     * Get redirect link with payment error code
     *
     * @param  int|string  $code
     *
     * @return  string|nullable
     */
    public function onPaymentError($code)
    {
        $order = $this->getOrder();

        if ( is_callable($callback = $this->onPaymentErrorCallback) ) {
            $message = $this->getOrderMessage($code);

            return $callback($order, $code, $message);
        }
    }

    public function getPaymentProvider($paymentMethodId = null)
    {
        $order = $this->getOrder();

        if ( !($paymentMethodId = $paymentMethodId ?: $order->payment_method_id) ){
            return;
        }

        $paymentClass = $this->getProviderById($this->paymentTypesConfigKey, $paymentMethodId);

        return $paymentClass;
    }

    public function isPaymentPaid(Payment $payment, $type = 'notification')
    {
        $paymentProvider = $this->setOrder($payment->getOrder())
                                ->getPaymentProvider($payment->payment_method_id);

        $paymentProvider->setPayment($payment);

        $redirect = null;

        try {
            $paymentProvider->isPaid(
                $paymentProvider->getPaymentId()
            );

            //Custom paid callback. We also can overide default redirect
            if ( method_exists($paymentProvider, 'onPaid') ){
                $redirect = $paymentProvider->onPaid($payment);
            }

            //Default paid callback
            else {
                //Update payment status
                $payment->onPaymentPaid();
            }

            //If redirect is not set yet
            if ( ! $redirect ){
                $redirect = redirect($this->onPaymentSuccess());
            }
        } catch (Exception $e){
            if ( $this->isDebug() ){
                throw $e;
            }

            $log = $order->logException($e, function($log) use ($e) {
                $log->code = $log->code ?: 'PAYMENT_ERROR';
            });

            $redirect = redirect($this->onPaymentError($log->code));
        }

        //Does not return redirect response on notification
        if ( in_array($type, ['notification']) ){
            return $paymentProvider->getNotificationResponse(
                $paymentProvider->getPaymentId()
            );
        }

        return $redirect;
    }
}
?>