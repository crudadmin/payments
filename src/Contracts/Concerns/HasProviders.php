<?php

namespace AdminPayments\Contracts\Concerns;

trait HasProviders
{
    /**
     * Returns provider class by given id type
     *
     * @param  string  $config
     * @param  ing  $id
     * @return Object
     */
    public function getProviderById(string $config, int $id = null)
    {
        $data = config($config);

        if ( !isset($data[$id]) ){
            return;
        }

        if ( is_string($data[$id]) ){
            $class = new $data[$id];
        } else {
            $class = new $data[$id]['provider']($data[$id]['options'] ?? []);
        }

        $class->setIdentifier($id);

        $order = $this->getOrder();

        //Returns booted provider class
        return $class->setOrder($order);
    }
}