<?php

namespace AdminPayments\Admin\Buttons;

use OrderService;
use Admin\Eloquent\AdminModel;
use Admin\Helpers\Button;
use Invoice;

class GenerateInvoice extends Button
{
    /*
     * Here is your place for binding button properties for each row
     */
    public function __construct(AdminModel $row)
    {
        //Name of button on hover
        $this->name = _('Vystaviť doklad');

        //Button classes
        $this->class = 'btn-default';

        //Button Icon
        $this->icon = 'fa-file-pdf-o';

        //Allow button only when invoices are created
        $this->active = OrderService::hasInvoices();
    }

    /*
     * Ask question with form before action
     */
    public function question($row)
    {
        if ( $row->items->count() == 0 ) {
            return $this->error(_('Objednávka neobsahuje žiadne položky k vygenerovaniu dokladu.'));
        }

        return $this->title(_('Naozaj si prajete vygenerovať faktúru?'))
                    ->component('AskForCreateOrderInvoice')
                    ->type('default');
    }

    /*
     * Firing callback on press button
     */
    public function fire(AdminModel $row)
    {
        if ( in_array($row->status, ['cancel']) ) {
            return $this->message(_('Táto objednávka bola zrušená, nie je možné jej vygenerovať doklad.'));
        }

        if ( !in_array($type = request('invoice_type'), ['proform', 'invoice', 'return']) ) {
            return $this->error(_('Nevybrali ste typ dokladu.'));
        }

        //Generate invoice by given type
        $row->makeInvoice($type);

        //Return invoice
        $invoice = $row->invoices()->where('type', $type)->first();

        return $this->downloadResponse($invoice);
    }

    public function downloadResponse($invoice)
    {
        if ( !$invoice || !($url = $invoice->getPdf()) ) {
            return $this->error(_('Doklad sa nepodarilo vygenerovať.'));
        }

        return $this->success(_('Doklad môžete stiahnuť na tejto adrese:').'<br> <a target="blank" href="'.$url.'">'.$url.'</a>');
    }
}