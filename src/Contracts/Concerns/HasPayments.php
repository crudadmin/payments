<?php

namespace AdminPayments\Contracts\Concerns;

use AdminPayments\Contracts\Concerns\HasPaymentHash;
use AdminPayments\Contracts\Concerns\HasPaymentLog;
use AdminPayments\Mail\PaymentPaid;
use AdminPayments\Models\Invoice\Invoice;
use Exception;
use Illuminate\Support\Facades\Mail;
use Log;
use PaymentService;
use Admin;

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
        return $this->getKey();
    }

    public function isPaid() : bool
    {
        return $this->payments()->whereNotNull('paid_at')->count() > 0 ? true : false;
    }

    public function getPaymentData($paymentMethodId = null)
    {
        $paymentMethodId = $paymentMethodId ?: $this->getPaymentMethodId();

        if ( !($paymentClass = $this->getPaymentProvider($paymentMethodId)->initialize()) ){
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

        if ( !($paymentClass = $this->getPaymentProvider($paymentMethodId)->initialize()) ){
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
     * Create order payment
     *
     * @param  AdminModel  $order
     * @param  int|null  $paymentMethodId
     *
     * @return  Payment
     */
    public function makePayment($paymentMethodId = null)
    {
        return $this->payments()->create([
            'table' => $this->getTable(),
            'row_id' => $this->getKey(),
            'price' => $this->price_vat ?: 0,
            'payment_method_id' => $paymentMethodId ?: $this->getPaymentMethodId(),
            'uniqid' => uniqid().str_random(10),
        ]);
    }

    public function sendPaymentEmail($invoice = null)
    {
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
}

?>