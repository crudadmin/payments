<?php

namespace AdminPayments\Contracts\Concerns;

trait HasPaymentHash
{
    public function getPaymentHash(string $type = 'default') : string
    {
        $key = implode('_', [
            env('APP_KEY'),
            $this->getTable(),
            $this->payment_method_id ?? null,
            $this->getKey(),
            $type
        ]);

        return hash('sha256', sha1(md5(sha1(md5($key)))));
    }
}