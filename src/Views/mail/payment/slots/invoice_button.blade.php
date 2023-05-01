@if ( isset($invoice) && $invoicePdf = $invoice->getPdf() )
@component('mail::button', ['url' => $invoicePdf->url])
    {{ _('Stiahnuť doklad') }}
@endcomponent
@endif