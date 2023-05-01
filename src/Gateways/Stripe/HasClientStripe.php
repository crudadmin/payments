<?php

namespace AdminPayments\Gateways\Stripe;

use AdminPayments\Gateways\Stripe\StripePayment;
use Stripe\Stripe;

trait HasClientStripe
{
    public function getStripeClient()
    {
        $stripe = new StripePayment([]);

        return $stripe->client;
    }

    public function getStripeCustomer($client = null)
    {
        $client = $client ?: client();
        $stripeClient = $this->getStripeClient();

        //If client is logged in
        //and stripe customer id field is present in clients
        if ( !$client || !$client->getField('stripe_customer_id')){
            return;
        }

        if ( $client->stripe_customer_id ){
            return $stripeClient->customers->retrieve($client->stripe_customer_id);
        }

        $customer = $stripeClient->customers->create([
            'name' => $client->clientName,
            'phone' => $client->phone,
            'email' => $client->email,
            'address' => [
              'city' => $client->city,
              'country' => $client->country?->code,
              'line1' => $client->street,
              'postal_code' => $client->zipcode,
            ],
        ]);

        $client->update([
            'stripe_customer_id' => $customer->id,
        ]);

        return $customer;
    }

    public function getStripeCustomerId($client = null)
    {
        $client = $client ?: client();

        //If client is logged in
        //and stripe customer id field is present in clients
        if ( !$client || !$client->getField('stripe_customer_id')){
            return;
        }

        if ( $client->stripe_customer_id ){
            return $client->stripe_customer_id;
        }

        return $this->getStripeCustomer($client)->id;
    }
}

?>