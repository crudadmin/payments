<?php

namespace AdminPayments\Contracts\Concerns;

use AdminPayments\Mail\PaymentPaid;
use AdminPayments\Models\Invoice\Invoice;
use Exception;
use Illuminate\Support\Facades\Mail;
use Log;
use PaymentService;

trait AdminModelPayments
{
    public function getPaymentData($paymentMethodId = null)
    {
        $paymentMethodId = $paymentMethodId ?: $this->payment_method_id;

        if ( !($paymentClass = PaymentService::bootPaymentProvider($this, $paymentMethodId)) ){
            return [];
        }

        return array_merge(
            [
                'provider' => class_basename(get_class($this->getPaymentProvider($paymentMethodId)))
            ],
            $paymentClass->getPaymentData(
                $paymentClass->getResponse()
            )
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

    public function getPaymentUrl($paymentMethodId = null)
    {
        $paymentMethodId = $paymentMethodId ?: $this->payment_method_id;

        if ( !($paymentClass = PaymentService::bootPaymentProvider($this, $paymentMethodId)) ){
            return [];
        }

        return $paymentClass->getPaymentUrl(
            $paymentClass->getResponse()
        );
    }

    public function getPostPaymentUrl($paymentMethodId = null)
    {
        $paymentMethodId = $paymentMethodId ?: $this->payment_method_id;

        if ( !($paymentClass = PaymentService::getPaymentProvider($paymentMethodId)) ){
            return;
        }

        return $paymentClass->getPostPaymentUrl(
            $paymentClass->getResponse()
        );
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
            Log::channel('payments')->error($e);

            $this->log()->create([
                'type' => 'error',
                'code' => 'email-payment-done-error',
            ]);
        }
    }
}

?>