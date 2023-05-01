<?php

namespace AdminPayments\Controllers\Payments;

use Admin;
use AdminPayments\Contracts\Order\Exceptions\OrderException;
use AdminPayments\Contracts\Payments\PaymentVerifier;
use AdminPayments\Contracts\Payments\Paypal\PaypalWebhooks;
use AdminPayments\Contracts\Payments\Stripe\StripeWebhooks;
use AdminPayments\Models\Orders\Order;
use AdminPayments\Models\Orders\Payment;
use Admin\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use OrderService;

class PaymentController extends Controller
{
    public function paymentStatus(Payment $payment, $type, $hash)
    {
        $order = $payment->order;

        //Check if is payment hash correct hash and ids
        if ( $hash != $order->makePaymentHash($type) ) {
            abort(401);
        }

        return OrderService::isPaymentPaid($payment, $order, $type);
    }

    public function postPayment($order, $hash)
    {
        $order = Admin::getModel('Order')->findOrFail($order);

        $type = 'postpayment';

        OrderService::setOrder($order);

        //Check if is payment hash correct hash and ids
        if ( $hash != $order->makePaymentHash($type) ) {
            abort(401);
        }

        //Order has been paid already
        if ( $order->paid_at ) {
            return redirect(OrderService::onPaymentError('PAYMENT_PAID'));
        }

        //If payment url could not be generated successfully
        if ( !($paymentUrl = $order->getPaymentUrl($order->payment_method_id)) ) {
            $paymentUrl = OrderService::onPaymentError('PAYMENT_ERROR');
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