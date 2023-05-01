<?php

namespace AdminPayments\Models\Payments;

use Admin\Eloquent\AdminModel;

class Payment extends AdminModel
{
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
                'status' => 'name:Status|max:10|default:waiting|index|required',
                'data' => 'name:Data|type:json',
            ],
            config('adminpayments.payment_methods.enabled', true)
                ? [ 'payment_method_id' => 'name:Typ platby|belongsTo:payments_methods,name|required' ] : [],
        );
    }
}