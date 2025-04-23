<?php

namespace AdminPayments\Gateways\Stripe\Concerns;

use Illuminate\Support\Facades\Cache;

trait HasStripeProduct
{
    /**
     * Get or create Stripe product
     *
     * @param string $key
     * @param array $options
     *
     * @return string
     */
    protected function getOrCreateProduct($key, $options)
    {
        return Cache::remember('product.'.$key, now()->addYear(1), function() use ($key, $options) {
            // Try to find existing product
            $products = $this->client->products->search([
                'query' => "metadata['sub_key']:'{$key}'",
                'limit' => 1,
            ]);

            if ($products->count() > 0) {
                return $products->data[0]->id;
            }

            // Create new product if not found
            $product = $this->client->products->create([
                'name' => $options['name'],
                'metadata' => [
                    'sub_key' => $key,
                ],
            ]);

            return $product->id;
        });
    }
}