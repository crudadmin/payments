<?php

namespace AdminPayments\Controllers;

use Admin;
use AdminPayments\Contracts\Order\Exceptions\OrderException;
use AdminPayments\Gateways\PaymentVerifier;
use AdminPayments\Gateways\Paypal\PaypalWebhooks;
use AdminPayments\Gateways\Stripe\StripeWebhooks;
use Admin\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use PaymentService;

class PaymentController extends Controller
{
    public function paymentStatus($paymentId, $type, $hash)
    {
        $payment = Admin::getModel('Payment')->findOrFail($paymentId);

        //Check if is payment hash correct hash and ids
        if ( $hash != $payment->getPaymentHash($type) ) {
            abort(401);
        }

        return PaymentService::isPaymentPaid($payment, $type);
    }

    public function postPayment($model, $orderId, $hash)
    {
        if ( !($order = Admin::getModelByTable($model)) ){
            abort(404);
        }

        $order = $order->findOrFail($orderId);

        $type = 'postpayment';

        //Check if is payment hash correct hash and ids
        if ( $hash != $order->getPaymentHash($type) ) {
            abort(401);
        }

        PaymentService::setOrder($order);

        //Order has been paid already
        if ( $order->isPaid() ) {
            return redirect(PaymentService::onPaymentError('PAYMENT_PAID'));
        }

        //If payment url could not be generated successfully
        if ( !($paymentUrl = $order->getPaymentUrl()) ) {
            $paymentUrl = PaymentService::onPaymentError('PAYMENT_ERROR');
        }

        return redirect($paymentUrl);
    }

    public function webhooks($type)
    {
        $hooks = [
            'stripe' => StripeWebhooks::class,
            'paypal' => PaypalWebhooks::class,
        ];

        if ( array_key_exists($type, $hooks) ){
            $webhook = new $hooks[$type];

            $event = $webhook->getWebhookEvent();

            return $webhook->onWebhookEvent(
                $event
            );
        }
    }
}