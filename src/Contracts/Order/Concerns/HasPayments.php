<?php

namespace AdminPayments\Contracts\Order\Concerns;

use Admin;
use AdminPayments\Contracts\Payments\GopayPayment;
use AdminPayments\Events\OrderPaid as OrderPaidEvent;
use AdminPayments\Models\Orders\Payment;
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

    public function hasOnlinePayment($paymentMethodId = null)
    {
        return $this->getPaymentProvider($paymentMethodId) ? true : false;
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

    /**
     * Create order payment
     *
     * @param  AdminModel  $order
     * @param  int|null  $paymentMethodId
     *
     * @return  Payment
     */
    private function makePayment($order, $paymentMethodId = null)
    {
        return $order->payments()->create([
            'price' => $order->price_vat,
            'payment_method_id' => $paymentMethodId ?: $order->payment_method_id,
            'uniqid' => uniqid().str_random(10),
        ]);
    }

    public function bootPaymentProvider($order, $paymentMethodId)
    {
        if ( !$this->hasOnlinePayment($paymentMethodId) ){
            return false;
        }

        return Admin::cache('payments.'.$order->getKey().'.'.$paymentMethodId.'.data', function() use ($order, $paymentMethodId) {
            try {
                $payment = $this->makePayment($order, $paymentMethodId);

                $paymentClass = $this->getPaymentProvider($paymentMethodId);

                $paymentClass->setPayment($payment);

                $paymentClass->setResponse(
                    $paymentClass->getPaymentResponse()
                );

                return $paymentClass;
            } catch (Exception $e){
                $order->logException($e, function($log){
                    $log->code = 'PAYMENT_INITIALIZATION_ERROR';
                });

                if ( $this->isDebug() ) {
                    throw $e;
                }
            }
        });
    }

    public function isPaymentPaid(Payment $payment, $order = null, $type = 'notification')
    {
        $paymentProvider = $this->setOrder($order)
                                ->getPaymentProvider($payment->payment_method_id)
                                ->setPayment($payment);

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
                $payment->update([ 'status' => 'paid' ]);

                $this->orderPaid();
            }

            //If redirect is not set yet
            if ( ! $redirect ){
                $redirect = redirect($this->onPaymentSuccess());
            }
        } catch (Exception $e){
            dd($e);
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

    public function orderPaid()
    {
        $order = $this->order;

        //If order is paid already
        if ( $order->paid_at ) {
            return;
        }

        event(new OrderPaidEvent($order));

        //Update order status paid
        $order->update([ 'paid_at' => Carbon::now() ]);

        //Countdown product stock on payment
        if ( config('adminpayments.stock.countdown.on_order_paid', true) == true ) {
            $order->syncStock('-', 'order.paid');
        }

        //Generate invoice
        $invoice = $this->hasInvoices() ? $this->makeInvoice('invoice') : null;

        //Send invoice email
        if ( config('adminpayments.mail.order.paid_notification', true) == true ) {
            $order->sendPaymentEmail($invoice);
        }
    }
}
?>