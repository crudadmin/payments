<?php

namespace AdminPayments\Contracts;

use AdminPayments\Concerns\Orderable;
use Admin\Core\Contracts\DataStore;
use Arr;
use Gogol\Invoices\Model\PaymentsMethod;

class ConfigProvider
{
    use DataStore;

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

    /**
     * Constructing of order provider
     *
     * @param array|mixed    options
     * @param string         identifier
     *
     * @param  mixed  $options
     */
    public function __construct($options = null)
    {
        $this->options = $options;
    }

    /**
     * AdminPayments\Contracts\Concerns\Orderable $order
     *
     * @param  [type]  $order
     */
    public function setOrder($order = null)
    {
        $this->order = $order;

        return $this;
    }

    public function setPaymentMethod(PaymentsMethod $paymentMethod)
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    public function setIdentifier($identifier)
    {
        $this->identifier = $identifier;

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

    public function toArray()
    {
        return [
            'name' => class_basename(get_class($this)),
            'options' => $this->getVisibleOptions(),
        ];
    }
}