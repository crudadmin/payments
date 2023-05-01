@component('mail::message')
# {{ sprintf(_('Dobrý deň %s'), $order->firstname) }},

@if ( isset($message) )
{{ $message }}
@endif

@include('admineshop::mail.order.slots.invoice_button')

@include('admineshop::mail.order.slots.footer')
@endcomponent
