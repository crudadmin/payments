<?php

namespace AdminPayments\Gateways\Stripe;

use AdminPayments\Gateways\Stripe\StripePayment;

trait HasStripeCustomer
{
    public function getStripeClient()
    {
        $stripe = new StripePayment([]);

        return $stripe->client;
    }

    public function getStripeCustomer($user = null)
    {
        $user = $user ?: client() ?: $this;
        $stripeClient = $this->getStripeClient();

        //If client is logged in
        //and stripe customer id field is present in clients
        if ( !$user || !$user->getField('stripe_customer_id')){
            return;
        }

        if ( $user->stripe_customer_id ){
            return $stripeClient->customers->retrieve($user->stripe_customer_id);
        }

        $customer = $stripeClient->customers->create([
            'name' => $user->payerName ?: $user->getValue('username') ?: $user->getValue('name'),
            'phone' => $user->phone,
            'email' => $user->email,
            'address' => [
              'city' => $user->city,
              'country' => $user->country?->code,
              'line1' => $user->street,
              'postal_code' => $user->zipcode,
            ],
        ]);

        $user->update([
            'stripe_customer_id' => $customer->id,
        ]);

        return $customer;
    }

    public function getStripeCustomerId($user = null)
    {
        $user = $user ?: client() ?: $this;

        //If client is logged in
        //and stripe customer id field is present in clients
        if ( !$user || !$user->getField('stripe_customer_id')){
            return;
        }

        if ( $user->stripe_customer_id ){
            return $user->stripe_customer_id;
        }

        return $this->getStripeCustomer($user)->id;
    }
}

?>