<?php

namespace AdminPayments\Models\Payments;

use AdminPayments\Contracts\Concerns\HasPaymentHash;
use AdminPayments\Contracts\Concerns\Orderable;
use AdminPayments\Events\PaymentPaid;
use Admin\Eloquent\AdminModel;
use Admin\Fields\Group;
use Carbon\Carbon;
use Exception;
use Log;
use PaymentService;

class Payment extends AdminModel
{
    use HasPaymentHash;

    /*
     * Model created date, for ordering tables in database and in user interface
     */
    protected $migration_date = '2017-12-12 17:02:10';

    /*
     * Template name
     */
    protected $name = 'Online platby';


    protected $active = false;

    protected $sortable = false;

    protected $publishable = false;

    /*
     * Automatic form and database generation
     * @name - field name
     * @placeholder - field placeholder
     * @type - field type | string/text/editor/select/integer/decimal/file/password/date/datetime/time/checkbox/radio
     * ... other validation methods from laravel
     */
    public function fields()
    {
        return array_merge(
            [
                'price' => 'name:Cena|type:decimal|required',
                'uniqid' => 'name:uniqid|max:30|required',
                'payment_id' => 'name:Payment id|index',
                Group::fields([
                    'payment_method_id' => 'name:Typ platby|belongsTo:payments_methods,name|required',
                ])->if(config('adminpayments.payment_methods.enabled', true)),
                'status' => 'name:Status|max:10|default:waiting|index|required',
                'paid_at' => 'name:Zaplatené dňa|type:datetime|hidden',
                'data' => 'name:Data|type:json',
            ],
        );
    }

    public function getOrder() : Orderable
    {
        throw new \Exception('No payment model defined.');
    }

    public function onPaymentPaid()
    {
        //If payment is paid already. Do nothing
        if ( $this->paid_at || $this->status == 'paid' ) {
            return;
        }

        $this->update([
            'status' => 'paid',
            'paid_at' => Carbon::now(),
        ]);

        $order = $this->getOrder();

        event(new PaymentPaid($this, $order));

        $order->onPaymentPaid();

        //Send notification email
        if ( $order->hasPaidNotification() ) {
            //Generate invoice
            $invoice = $order->makeInvoice('invoice');

            $order->sendPaymentEmail($invoice);
        }
    }

    public function isPaymentPaid($type = 'notification')
    {
        $provider = PaymentService::setOrder($this->getOrder())
                                    ->getPaymentProvider($this->payment_method_id);

        $provider->setPayment($this);

        $redirect = null;

        try {
            $provider->isPaid(
                $provider->getPaymentId()
            );

            //Custom paid callback. We also can overide default redirect
            if ( method_exists($provider, 'onPaid') ){
                $redirect = $provider->onPaid($this);
            }

            //Default paid callback
            else {
                //Update payment status
                $this->onPaymentPaid();
            }

            //If redirect is not set yet
            if ( ! $redirect ){
                $redirect = redirect(PaymentService::onPaymentSuccess());
            }
        } catch (Exception $e){
            if ( PaymentService::isDebug() ){
                throw $e;
            }

            $log = $this->getOrder()->logException($e, function($log) use ($e) {
                $log->code = $log->code ?: 'PAYMENT_ERROR';
            });

            $redirect = redirect(PaymentService::onPaymentError($log->code));
        }

        //Does not return redirect response on notification
        if ( in_array($type, ['notification']) ){
            return $provider->getNotificationResponse(
                $provider->getPaymentId()
            );
        }

        return $redirect;
    }
}