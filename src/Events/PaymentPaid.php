<?php

namespace AdminPayments\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class PaymentPaid
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $payment;
    public $order;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($payment, $order)
    {
        $this->payment = $payment;
        $this->order = $order;
    }

    public function getPayment()
    {
        return $this->payment;
    }

    public function getOrder()
    {
        return $this->order;
    }
}
