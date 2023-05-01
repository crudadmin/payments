@component('mail::message')
# {{ sprintf(_('Dobrý deň %s'), $order->firstname) }},

@if ( isset($message) )
{{ $message }}
@endif

@include('adminpayments::mail.payment.slots.invoice_button')

@include('adminpayments::mail.payment.slots.footer')
@endcomponent
