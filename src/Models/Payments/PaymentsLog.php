<?php

namespace AdminPayments\Models\Payments;

use Admin\Eloquent\AdminModel;
use Admin\Fields\Group;
use Admin;
use DB;

class PaymentsLog extends AdminModel
{
    /*
     * Model created date, for ordering tables in database and in user interface
     */
    protected $migration_date = '2020-03-27 06:49:15';

    /*
     * Template name
     */
    protected $name = 'Hlásenia';

    /*
     * Template title
     * Default ''
     */
    protected $title = '';

    protected $icon = 'fa-exclamation-triangle';

    protected $publishable = false;

    protected $sortable = false;

    protected $insertable = false;

    protected $active = false;


    protected $settings = [
        'title.insert' => 'Nové hlásenie',
    ];

    public $timestamps = false;

    /*
     * Automatic form and database generation
     * @name - field name
     * @placeholder - field placeholder
     * @type - field type | string/text/editor/select/integer/decimal/file/password/date/datetime/time/checkbox
     * ... other validation methods from laravel
     */
    public function fields()
    {
        return [
            'table' => 'name:Model|max:30|index',
            'row_id' => 'name:Row id|type:integer|index|required',
            'type' => 'name:Typ hlásenia|type:select|default:info|required',
            'code' => 'name:Kód hlásenia|type:select',
            'message' => 'name:Doplnková správa',
            'log' => 'name:Log|type:text',
            'created_at' => 'name:Vytvorené|type:datetime|default:CURRENT_TIMESTAMP',
        ];
    }

    public function options()
    {
        return [
            'type' => [
                'info' => 'Informácia',
                'error' => 'Chyba',
                'success' => 'Úspech',
            ],
            'code' => config('adminpayments.order.codes', []),
        ];
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
}