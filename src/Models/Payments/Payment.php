<?php

namespace AdminPayments\Models\Payments;

use AdminPayments\Contracts\Concerns\HasPaymentHash;
use AdminPayments\Contracts\Concerns\Orderable;
use AdminPayments\Events\PaymentPaid;
use Admin\Eloquent\AdminModel;
use Admin\Fields\Group;
use Carbon\Carbon;
use Localization;
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
                Group::fields([
                    'language' => 'name:Jazyk|belongsTo:languages,name',
                ])->if(Admin::isEnabledLocalization()),
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

    public function setLocale()
    {
        if ( $this->language_id ){
            $language = Localization::get();

            if ( $this->language_id != $language->getKey() ){
                $paymentLocale = Localization::all()->firstWhere('id', $this->language_id);

                Localization::setLocale($paymentLocale->slug);
            }
        }

        return $this;
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

    public function setPaymentData($data)
    {
        if ( is_array($data) && count($data) ) {
            $this->data = array_merge($this->data ?: [], $data);
        }

        return $this;
    }

    public function getIsPaidAttribute()
    {
        //!!! TESTING PAYMENT: UNCOMENT THIS ONLY DURING TESTING SINGLE PAYMENT !!!
        if ( app()->environment('local') && app()->hasDebugModeEnabled() && config('adminpayments.testing', false) === true ){
            return false;
        }

        return $this->paid_at || $this->status == 'paid';
    }

    /**
     * When order is paid for the first time
     *
     * @param  string  $processType
     */
    public function setPaymentPaid($processType = null)
    {
        if ( $this->isPaid ) {
            return;
        }

        $this->status = 'paid';
        $this->paid_at = Carbon::now();

        if ( $processType ) {
            $this->setPaymentData([
                'paid_by' => $processType,
            ]);
        }

        $this->save();

        if ( $order = $this->order ) {
            event(new PaymentPaid($this, $order));

            $order->setPaymentPaid($this);

            //Send notification email
            if ( $order->hasPaidNotification() ) {
                //Generate invoice
                $invoice = $order->makeInvoice('invoice');

                $order->sendPaymentEmail($invoice);
            }
        }
    }

    /**
     * When payment is paid already
     * but we received status update
     *
     * @param  string  $paymentId
     * @param  string|nullable  $webhookName
     */
    public function setPaymentCheck($webhookName = null)
    {
        if ( $this->order ){
            $this->order->setPaymentCheck($this, $webhookName);
        }
    }

    public function getPaymentProvider()
    {
        $provider = PaymentService::setOrder($this->order)
                                    ->getPaymentProvider($this->payment_method_id);

        $provider->setPayment($this);

        return $provider;
    }

    public function paymentStatusResponse($type = 'notification', $webhookName = null)
    {
        $provider = $this->getPaymentProvider();

        try {
            //Check paid status
            if ( $this->isPaid == false ) {
                $provider->isPaid(
                    $provider->getPaymentId()
                );

                $provider->onPaid($type);
            }

            //Check payment existance status
            else {
                $provider->onCheck(
                    $provider->getPaymentId(),
                    $webhookName,
                );
            }

            //If redirect is not set yet
            $response = $provider->getSuccessResponse();
        } catch (Exception $e){
            if ( PaymentService::isDebug() ){
                throw $e;
            }

            $log = $this->order->logException($e, function($log) use ($e) {
                $log->code = $log->code ?: 'PAYMENT_ERROR';
            });

            $response = $provider->getErrorResponse($log);
        }

        //Does not return redirect response on notification
        if ( in_array($type, ['notification', 'webhook']) ){
            $response = $provider->getNotificationResponse();
        }

        return $response ?? null;
    }

    public function onWebhookEvent($webhookName)
    {
        //Save triggered webhooks
        $this->setPaymentData([
            'webhooks' => array_unique(array_merge($this->data['webhooks'] ?? [], [
                Carbon::now()->format('Y-m-d H:i:s') => $webhookName
            ])),
        ])->save();

        return $this->paymentStatusResponse('webhook', $webhookName);
    }
}