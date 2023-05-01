<?php

namespace AdminPayments\Contracts\Concerns;

interface Orderable
{
    public function getPaymentData();

    public function getPaymentUrl();

    public function getPostPaymentUrl();
}