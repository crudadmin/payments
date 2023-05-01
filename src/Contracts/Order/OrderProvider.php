<?php

namespace AdminPayments\Contracts\Order;

use AdminPayments\Models\Delivery\Delivery;
use AdminPayments\Models\Orders\Order;
use AdminPayments\Models\Store\PaymentsMethod;
use Arr;

class OrderProvider
{
    protected $options = [];

    /**
     * Which options keys are visible to frontend
     *
     * @return  array
     */
    protected $visibleOptionsKeys = [];

    protected $identifier;

    protected $order;

    protected $paymentMethod;

    protected $delivery;

    /**
     * Constructing of order provider
     *
     * @param  mixed  $options
     */
    public function __construct($options = null, $identifier = null)
    {
        $this->options = $options;

        $this->identifier = $identifier;
    }

    public function setOrder(Order $order = null)
    {
        $this->order = $order;

        return $this;
    }

    public function setDelivery($delivery)
    {
        $this->delivery = $delivery;

        return $this;
    }

    public function setPaymentMethod($paymentMethod)
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    public function setOptions($options)
    {
        $this->options = $options;

        return $this;
    }

    public function getIdentifier()
    {
        return $this->identifier;
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function getOptions()
    {
        return $this->options ?: [];
    }

    public function getVisibleOptions()
    {
        $options = $this->getOptions();

        return array_intersect_key($options, array_flip($this->visibleOptionsKeys));
    }

    public function getOption($key, $default = null)
    {
        $options = $this->options ?: [];

        $value = Arr::get($options, $key);

        return is_null($value) ? $default : $value;
    }

    public function getPaymentMethod()
    {
        return $this->paymentMethod;
    }

    public function getDelivery()
    {
        return $this->delivery;
    }

    public function toArray()
    {
        return [
            'name' => class_basename(get_class($this)),
            'options' => $this->getVisibleOptions(),
        ];
    }
}