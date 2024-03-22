<?php

namespace AdminPayments\Contracts\Concerns;

use Admin;
use AdminPayments\Contracts\Concerns\HasPaymentHash;
use AdminPayments\Contracts\Concerns\HasPaymentLog;
use AdminPayments\Mail\PaymentPaid;
use AdminPayments\Models\Payments\Payment;
use AdminPayments\Models\Payments\PaymentsLog;
use Exception;
use Gogol\Invoices\Model\Invoice;
use Illuminate\Support\Facades\Mail;
use Localization;
use Log;
use PaymentService;

trait HasPayments
{
    use HasPaymentHash,
        HasPaymentLog;

    public function hasInvoices()
    {
        return config('adminpayments.invoices.enabled', false);
    }

    public function hasPaidNotification()
    {
        return config('adminpayments.notificaions.paid', true);
    }

    public function getNumberAttribute()
    {
        return str_pad($this->getKey(), 5, '0', STR_PAD_LEFT);
    }

    public function isPaid() : bool
    {
        return $this->payments()->whereNotNull('paid_at')->count() > 0 ? true : false;
    }

    public function getPaymentData($paymentMethodId = null)
    {
        $paymentMethodId = $paymentMethodId ?: $this->getPaymentMethodId();

        if ( !($paymentClass = $this->getPaymentProvider($paymentMethodId)?->initialize()) ){
            return [];
        }

        return array_merge(
            [
                'provider' => class_basename($paymentClass::class)
            ],
            $paymentClass->getPaymentData(
                $paymentClass->getResponse()
            )
        );
    }

    public function getPaymentUrl($paymentMethodId = null)
    {
        $paymentMethodId = $paymentMethodId ?: $this->getPaymentMethodId();

        if ( !($paymentClass = $this->getPaymentProvider($paymentMethodId)?->initialize()) ){
            return [];
        }

        return $paymentClass->getPaymentUrl(
            $paymentClass->getResponse()
        );
    }

    public function getPostPaymentUrl($paymentMethodId = null)
    {
        $paymentMethodId = $paymentMethodId ?: $this->getPaymentMethodId();

        if ( !($paymentClass = $this->getPaymentProvider($paymentMethodId)) ){
            return;
        }

        return $paymentClass->getPostPaymentUrl(
            $paymentClass->getResponse()
        );
    }

    public function getPaymentDataAttribute()
    {
        return $this->getPaymentData();
    }

    public function getPostPaymentUrlAttribute()
    {
        return $this->getPostPaymentUrl();
    }

    public function getPaymentProvider($paymentMethodId = null)
    {
        return PaymentService::setOrder($this)->getPaymentProvider($paymentMethodId);
    }

    public function getPaidNotificationContent()
    {
        return [
            'subject' => sprintf(_('Potvrdenie platby k objednávke č. %s'), $this->number),
            'content' => _('Vaša objednávka bola úspešne dokončená a zaplatená. Ďakujeme!'),
        ];
    }

    /**
     * Which number should be paid
     */
    public function getTotalToPay()
    {
        if ( $this->getField('price_vat') ) {
            return $this->price_vat ?: 0;
        }

        if ( $this->getField('price') ) {
            return $this->price ?: 0;
        }

        return 0;
    }

    /**
     * Create order payment
     *
     * @param  AdminModel  $order
     * @param  int|null  $paymentMethodId
     *
     * @return  Payment
     */
    public function makePayment($paymentMethodId = null)
    {
        $data = [
            'table' => $this->getTable(),
            'row_id' => $this->getKey(),
            'price' => $this->getTotalToPay(),
            'payment_method_id' => $paymentMethodId ?: $this->getPaymentMethodId(),
            'uniqid' => uniqid().str_random(10),
        ];

        if ( Admin::isEnabledLocalization() ){
            $data['language_id'] = Localization::get()->getKey();
        }

        return $this->payments()->create($data);
    }

    public function sendPaymentEmail($invoice = null)
    {
        if ( !$this->email ) {
            return;
        }

        try {
            Mail::to($this->email)->send(
                new PaymentPaid($this, $invoice)
            );

            if ( $invoice instanceof Invoice ) {
                $invoice->setNotified();
            }
        } catch (Exception $e){
            Log::error($e);

            $this->log()->create([
                'type' => 'error',
                'code' => 'email-payment-done-error',
            ]);
        }
    }

    public function log()
    {
        return $this->hasMany(PaymentsLog::class);
    }

    /**
     * We will fetch selected payment method from this column
     *
     * @return  int
     */
    public function getPaymentMethodId()
    {
        return $this->payment_method_id;
    }

    /**
     * Overall payment description
     *
     * @return  string|nullable
     */
    public function getPaymentDescription()
    {

    }

    /**
     * On paid order check
     *
     * @param  Payment  $payment
     * @param  string|optional  $webhookName
     */
    public function setPaymentCheck(Payment $payment, $webhookName)
    {
        //..
    }

    /**
     * On payment paid
     *
     * @param  Payment  $payment
     */
    public function setPaymentPaid(Payment $payment)
    {
        //...
    }

    public function getAfterPaymentRoute()
    {
        return '/';
    }

    public function payments()
    {
        return $this
                ->hasMany(
                    Admin::getModel('Payment')::class,
                    'row_id',
                    'id',
                )
                ->where('payments.table', $this->getTable());
    }

    /**
     * Returns payment description name
     *
     * @return  string
     */
    public function getPaymentTitle($number)
    {
        return 'Order n. '.$number;
    }
}

?>