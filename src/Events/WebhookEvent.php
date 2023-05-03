<?php

namespace AdminPayments\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class WebhookEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $name;
    public $event;
    public $payment;
    public $class;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($name, $event, $payment, $class)
    {
        $this->name = $name;
        $this->event = $event;
        $this->payment = $payment;
        $this->class = $class;
    }
}