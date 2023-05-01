<?php
namespace AdminPayments\Facades;

use Illuminate\Support\Facades\Facade;

class PaymentServiceFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'admin.payments.service';
    }
}