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

    /*
     * Returns if invoices support is allowed
     */
    public function hasInvoices()
    {
        return config('adminpayments.invoices', false) === true;
    }

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

    public function routesForPayments()
    {
        Route::group(['namespace' => '\AdminPayments\Controllers'], function(){
            Route::get('/_store/payments/create/{payment}/{type}/{hash}', 'PaymentController@paymentStatus');
            Route::get('/_store/payments/post-payment/{order}/{hash}', 'PaymentController@postPayment');
            Route::any('/_store/payments/webhooks/{type}', 'PaymentController@webhooks');
        });
    }

    /**
     * Generate invoice for order
     *
     * @return  Invoice|null
     */
    public function makeInvoice($type = 'proform', $data = [])
    {
        if ( ! $this->hasInvoices() ) {
            return;
        }

        $order = $this->getOrder();

        try {
            //Generate proform
            $invoice = $order->makeInvoice($type, $data);

            //Set unpaid proform as paid
            if ( $invoice->type == 'invoice' && $invoice->paid_at && $invoice->proform && !$invoice->proform->paid_at ){
                $invoice->proform->update([
                    'paid_at' => $invoice->paid_at,
                ]);
            }

            return $invoice;
        } catch (Exception $error){
            Log::error($error);

            $order->log()->create([
                'type' => 'error',
                'code' => 'INVOICE_ERROR',
                'log' => $error->getMessage()
            ]);

            //Debug
            if ( $this->isDebug() ) {
                throw $error;
            }
        }
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