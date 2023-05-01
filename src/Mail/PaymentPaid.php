<?php

namespace AdminPayments\Mail;

use AdminPayments\Contracts\Collections\CartCollection;
use AdminPayments\Models\Orders\Order;
use Cart;
use Discounts;
use Gogol\Invoices\Model\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentPaid extends Mailable
{
    use Queueable, SerializesModels;

    private $order;
    private $invoice;
    private $message;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Order $order, Invoice $invoice = null, $message = null)
    {
        $this->order = $order;
        $this->invoice = $invoice;
        $this->message = $message;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {

        $mail = $this
                //Ability to rewrite subject if is not set
                ->subject(
                    $this->subject ?: sprintf(_('Potvrdenie platby k objednávke č. %s'), $this->order->number)
                )
                ->markdown('admineshop::mail.order.paid', [
                    'message' => $this->message ?: _('Vaša objednávka bola úspešne dokončená a zaplatená. Ďakujeme!'),
                    'order' => $this->order,
                    'invoice' => $this->invoice,
                ]);

        if ( $this->invoice && $pdf = $this->invoice->getPdf() ) {
            $mail->attachData($pdf->get(), $pdf->filename);
        }

        return $mail;

    }
}
