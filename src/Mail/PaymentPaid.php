<?php

namespace AdminPayments\Mail;

use AdminPayments\Contracts\Concerns\Orderable;
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
    public function __construct(Orderable $order, Invoice $invoice = null, $message = null)
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
        $order = $this->order;

        $content = $order->getPaidNotificationContent();

        $mail = $this
                //Ability to rewrite subject if is not set
                ->subject($this->subject ?: $content['subject'])
                ->markdown('adminpayments::mail.payment.paid', [
                    'message' => $this->message ?: $content['content'],
                    'order' => $order,
                    'invoice' => $this->invoice,
                ]);

        if ( $this->invoice && $pdf = $this->invoice->getPdf() ) {
            $mail->attachData($pdf->get(), $pdf->filename);
        }

        return $mail;

    }
}
