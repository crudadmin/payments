<?php

namespace AdminPayments\Contracts\Concerns;

use AdminPayments\Models\Payments\Payment;
use Gogol\Invoices\Model\Invoice;

interface Orderable
{
    /**
     * Return initialized payment response data
     *
     * @return  mixed|array
     */
    public function getPaymentData();

    /**
     * Returns payment url where we can continue on purchase
     *
     * @return  string
     */
    public function getPaymentUrl();

    /**
     * On this url we can initialize payment any time
     *
     * @return  string
     */
    public function getPostPaymentUrl();

    /**
     * Number of order
     *
     * @return  string
     */
    public function getNumberAttribute();

    /**
     * Which payment method should be used for payment creation
     *
     * @return  int
     */
    public function getPaymentMethodId();

    /**
     * Determine whatever order is paid
     *
     * @return  bool
     */
    public function isPaid() : bool;

    /**
     * On successfull payment
     */
    public function onPaymentPaid(Payment $payment);

    /**
     * Should we generate invoice for a paid order?
     *
     * @return  bool
     */
    public function hasInvoices();

    /**
     * Should we send email notification with successfull payment?
     *
     * @return  bool
     */
    public function hasPaidNotification();

    /**
     * Generate and return invoice
     *
     * @return  Gogol\Invoices\Model\Invoice|null
     */
    public function makeInvoice();

    /**
     * Returns payment hash for verification
     *
     * @return  string
     */
    public function getPaymentHash() : string;

    /**
     * Returns overall payment/order description
     *
     * @return  mixed|null
     */
    public function getPaymentDescription();
}