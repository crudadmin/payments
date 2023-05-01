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
}
?>