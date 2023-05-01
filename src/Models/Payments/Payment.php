<?php

namespace AdminPayments\Models\Payments;

use AdminPayments\Contracts\Concerns\HasPaymentHash;
use AdminPayments\Contracts\Concerns\Orderable;
use AdminPayments\Events\PaymentPaid;
use Admin\Eloquent\AdminModel;
use Admin\Fields\Group;
use Carbon\Carbon;
use Exception;
use Admin;
use Log;
use PaymentService;
use DB;

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
                'uniqid' => 'name:uniqid|max:30|required',
                'table' => 'name:Model|max:30|index',
                'row_id' => 'name:Row id|type:integer|index|required',
                'payment_id' => 'name:Payment id|index',
                'price' => 'name:Cena|type:decimal|required',
                Group::fields([
                    'payment_method_id' => 'name:Typ platby|belongsTo:payments_methods,name|required',
                ])->if(config('adminpayments.payment_methods.enabled', true)),
                'status' => 'name:Status|max:10|default:waiting|index|required',
                'paid_at' => 'name:Zaplatené dňa|type:datetime|hidden',
                'data' => 'name:Data|type:json',
            ],
        );
    }

    /*
     * Add empty rows
     */
    public function onMigrateEnd($table, $schema)
    {
        if ( $schema->hasColumn($this->getTable(), 'order_id') ) {
            $this->getConnection()->table($this->getTable())
                ->whereNull('table')
                ->whereNotNull('order_id')
                ->update([
                    'table' => 'orders',
                    'row_id' => DB::raw('order_id'),
                ]);
        }
    }

    /**
     * Returns relation model
     *
     * @return  Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function order()
    {
        $relatedModel = Admin::getModelByTable($this->getValue('table'));

        return $this->hasOne($relatedModel::class, 'id', 'row_id');
    }

    public function onPaymentPaid($processType = null)
    {
        //If payment is paid already. Do nothing
        if ( $this->paid_at || $this->status == 'paid' ) {
            return;
        }

        $this->status = 'paid';
        $this->paid_at = Carbon::now();

        if ( $processType ) {
            $this->data = ($this->data ?: []) + [
                'paid_by' => $processType,
            ];
        }

        $this->save();

        $order = $this->order;

        event(new PaymentPaid($this, $order));

        $order->onPaymentPaid($this);

        //Send notification email
        if ( $order->hasPaidNotification() ) {
            //Generate invoice
            $invoice = $order->makeInvoice('invoice');

            $order->sendPaymentEmail($invoice);
        }
    }

    public function getPaymentProvider()
    {
        $provider = PaymentService::setOrder($this->order)
                                    ->getPaymentProvider($this->payment_method_id);

        $provider->setPayment($this);

        return $provider;
    }

    public function isPaymentPaid($type = 'notification')
    {
        $provider = $this->getPaymentProvider();

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
                $this->onPaymentPaid($type);
            }

            //If redirect is not set yet
            if ( ! $redirect ){
                $redirect = redirect(PaymentService::onPaymentSuccess());
            }
        } catch (Exception $e){
            if ( PaymentService::isDebug() ){
                throw $e;
            }

            $log = $this->order->logException($e, function($log) use ($e) {
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