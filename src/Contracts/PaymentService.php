<?php

namespace AdminPayments\Contracts;

use Admin;
use Route;
use AdminPayments\Contracts\Concerns\HasPayments;
use AdminPayments\Contracts\Concerns\HasProviders;
use Admin\Core\Contracts\DataStore;
use Exception;
use Log;
use Mail;
use Store;

class PaymentService
{
    use DataStore,
        HasProviders,
        HasPayments;

    /**
     * Order row
     *
     * @var  Admin\Eloquent\AdminModel|null
     */
    protected $order;

    protected $paymentTypesConfigKey = 'adminpayments.payment_methods.providers';

    protected $onPaymentSuccessCallback = null;

    protected $onPaymentErrorCallback = null;

    /**
     * Set order
     *
     * @param  AdminModel|null  $order
     */
    public function setOrder($order, $discounts = false, $items = false)
    {
        $this->order = $order;

        return $this;
    }

    /**
     * Returns order
     *
     * @return  null|Admin\Eloquent\AdminModel
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * Set successfull payment response callback
     *
     * @param  callable  $callback
     */
    public function setOnPaymentSuccess(callable $callback)
    {
        $this->onPaymentSuccessCallback = $callback;
    }

    /**
     * Set error payment response callback
     *
     * @param  callable  $callback
     */
    public function setOnPaymentError(callable $callback)
    {
        $this->onPaymentErrorCallback = $callback;
    }

    /**
     * Get payment success response
     *
     * @return  callable
     */
    public function onPaymentSuccess()
    {
        $order = $this->getOrder();

        if ( is_callable($callback = $this->onPaymentSuccessCallback) ) {
            return $callback($order);
        }

        $path = $order->getAfterPaymentRoute();
        $path .= (strpos($path, '?') ? '&' : '?').'id='.$order->getKey();
        $path .= '&hash='.$order->getHash();
        $path .= '&paymentSuccess=1';

        return env('APP_NUXT_URL').$path;
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

        $errorMessage = $this->getOrderMessage($code);

        if ( is_callable($callback = $this->onPaymentErrorCallback) ) {
            return $callback($order, $code, $errorMessage);
        }

        $path = $order->getAfterPaymentRoute();
        $path .= (strpos($path, '?') ? '&' : '?').'id='.$order->getKey();
        $path .= '&hash='.$order->getHash();
        $path .= '&paymentError='.$code;
        $path .= '&paymentMessage='.$errorMessage;

        return env('APP_NUXT_URL').$path;
    }

    /**
     * Returns Payment provider
     *
     * @param  int|nullale  $paymentMethodId
     *
     * @return  AdminPayments\Gateways\PaymentGateway|null
     */
    public function getPaymentProvider($paymentMethodId = null)
    {
        $order = $this->getOrder();

        if ( !($paymentMethodId = $paymentMethodId ?: $order->payment_method_id) ){
            return;
        }

        $paymentClass = $this->getProviderById($this->paymentTypesConfigKey, $paymentMethodId);

        return $paymentClass;
    }

    public function routesForPayments()
    {
        Route::group(['namespace' => '\AdminPayments\Controllers'], function(){
            Route::get('/_store/payments/create/{payment}/{type}/{hash}', 'PaymentController@paymentStatus');
            Route::get('/_store/payments/post-payment/{model}/{orderId}/{hash}', 'PaymentController@postPayment');
            Route::any('/_store/payments/webhooks/{type}', 'PaymentController@webhooks');
        });
    }

    public function isDebug()
    {
        return app()->environment('local') && env('APP_DEBUG') == true && env('APP_STORE_DEBUG') == true;
    }

    public function getOrderMessage($key)
    {
        return config('adminpayments.order.codes.'.$key);
    }
}

?>