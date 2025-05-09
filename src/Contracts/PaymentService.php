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

    protected $onPaymentUrl = '';

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

    public function setPaymentUrl($url)
    {
        $this->onPaymentUrl = $url;

        return $this;
    }

    /**
     * Set successfull payment response callback
     *
     * @param  callable  $callback
     */
    public function setOnPaymentSuccess(callable $callback)
    {
        $this->onPaymentSuccessCallback = $callback;

        return $this;
    }

    /**
     * Set error payment response callback
     *
     * @param  callable  $callback
     */
    public function setOnPaymentError(callable $callback)
    {
        $this->onPaymentErrorCallback = $callback;

        return $this;
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

        $path = $this->addQueryIntoUrl(
            $order->getAfterPaymentRoute(),
            'id='.$order->getKey().'&hash='.$order->getHash().'&paymentSuccess=1',
        );

        return $this->url($path);
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

        $errorMessage = $this->getPaymentMessage($code);

        if ( is_callable($callback = $this->onPaymentErrorCallback) ) {
            return $callback($order, $code, $errorMessage);
        }

        $path = $this->addQueryIntoUrl(
            $order->getAfterPaymentRoute(),
            'id='.$order->getKey().'&hash='.$order->getHash().'&paymentError='.$code.'&paymentMessage='.$errorMessage,
        );

        return $this->url($path);
    }

    /**
     * Build final query with acceptance for additional queries and sub hashtags for vue routes.
     */
    private function addQueryIntoUrl($url, $query)
    {
        $hashParts = explode('#', $url);

        $host = $hashParts[0];

        //Support for queries which has query already
        $query = (strpos($host, '?') ? '&' : '?').$query;

        $hash = (isset($hashParts[1]) ? '#'.$hashParts[1] : '');

        //Support for queries with hashes
        return $host.$query.$hash;
    }

    /**
     * Returns payment url
     *
     * @param  string  $path
     *
     * @return  string
     */
    public function url($path)
    {
        //If full url is made already
        $isHttp = starts_with($path, 'http://') || starts_with($path, 'https://');

        $paymentUrl = $this->onPaymentUrl;

        $origin = request('origin');

        if ( is_callable($paymentUrl) ){
            return $paymentUrl($path, $origin);
        }

        $url = $paymentUrl ?: $origin ?: url('');

        return ($isHttp ? '' : $url).$path;
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

        if ( !($paymentMethodId = $paymentMethodId ?: $order->getPaymentMethodId()) ){
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
        $isTesting = (env('APP_STORE_DEBUG') == true) || config('adminpayments.testing', false) == true;

        return app()->environment('local') && env('APP_DEBUG') == true && $isTesting;
    }

    public function getPaymentMessage($key)
    {
        $codes = array_merge(
            [
                'PAYMENT_INITIALIZATION_ERROR' => _('Platbu nebolo možné inicializovať.'),
                'PAYMENT_ERROR' => _('Nastala nečakaná chyba pri spracovani platby. Skúste platbu vykonať neskôr, alebo nás prosím kontaktujte.'),
                'PAYMENT_UNVERIFIED' => _('Vaša objednávka bola úspešne zaznamenaná, no potvrdenie Vašej platby sme zatiaľ neobdržali. V prípade ak ste platbu nevykonali, môžete ju uhradiť opätovne z emailu, alebo nás kontaktujte pre ďalšie informácie.'),
                'PAYMENT_PAID' => _('Vaša objednávka už bola úspešne zaplatená.'),
                'INVOICE_ERROR' => _('Chyba vygenerovania dokladu.'),
            ],
            config('adminpayments.error_codes', []),
            config('admineshop.order.codes', []),
        );

        return $codes[$key] ?? null;
    }
}

?>