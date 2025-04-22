<?php

namespace AdminPayments\Gateways\Stripe\Concerns;

use Exception;
use Carbon\Carbon;
use AdminPayments\Contracts\Exceptions\PaymentGateException;

trait HasStripeSubscription
{
    /**
     * Fetch subscription from Stripe and cache it
     *
     * @param  mixed $id
     * @return void
     */
    public function getStripeSubscription($id)
    {
        return $this->cache('id.'.$id, function() use ($id) {
            // Get by subscription ID
            if ( str_starts_with($id, 'sub_') ) {
                return $this->client->subscriptions->retrieve($id);
            }

            // Get subscription by checkout session ID
            $session = $this->client->checkout->sessions->retrieve($id);

            $subscriptionId = $session->subscription;

            // Set payment ID as subscription
            $this->setPaymentId($subscriptionId, [ 'session_id' => $id ]);

            return $this->client->subscriptions->retrieve($subscriptionId);
        });
    }

    /**
     * Check subscription status and return subscription data
     *
     * @param mixed $payment
     * @return array
     */
    public function getSubscription($subscriptionId)
    {
        $subscription = $this->getStripeSubscription($subscriptionId);

        $isActive = in_array($subscription->status, ['active', 'trialing']);

        $periodEnd = $subscription->items->first()?->current_period_end;

        return [
            'active' => $isActive,
            'paused' => $subscription->cancel_at_period_end === true,
            'valid_to' => $periodEnd ? Carbon::createFromTimestamp($periodEnd) : null,
            'trial_valid_to' => $subscription->trial_end ? Carbon::createFromTimestamp($subscription->trial_end) : null,
        ];
    }

    /**
     * (UNUSED CODE)
     * Recreate subscription with new price from previous subscription
     *
     * @param  mixed $subscriptionId
     * @return void
     */
    public function recreateSubscriptionManually($subscriptionId)
    {
        $order = $this->getOrder();

        try {
            // Get current subscription
            $oldSubscription = $this->getStripeSubscription($subscriptionId);

            // Get default payment method from current subscription
            $defaultPaymentMethod = $oldSubscription->default_payment_method;

            // Get current period end from the first subscription item
            // But only if subscription is active or trialing
            $currentPeriodEnd = in_array($oldSubscription->status, ['active', 'trialing']) ? $oldSubscription->items->first()?->current_period_end : null;

            $data = array_filter([
                'customer' => $oldSubscription->customer,
                'items' => [[
                    'price_data' => $this->getPriceData($order),
                ]],
                'metadata' => [
                    'order_number' => $order->number,
                ],
                'trial_end' => $currentPeriodEnd,
                'default_payment_method' => $defaultPaymentMethod,
            ]);

            // Create new subscription with updated price
            $newSubscription = $this->client->subscriptions->create($data);

            $id = $newSubscription->id;

            $this->setPaymentId($id);

            return $id;
        } catch (Exception $e) {
            throw new PaymentGateException($e->getMessage());
        }
    }

    /**
     * Pause or resume subscription
     *
     * @param string $subscriptionId
     * @param bool $pause
     * @return void
     */
    public function pauseSubscription($subscriptionId, $pause = true)
    {
        $this->client->subscriptions->update($subscriptionId, [
            'cancel_at_period_end' => $pause,
        ]);
    }

    /**
     * Removes subscription
     *
     * @param  mixed $subscriptionId
     * @return void
     */
    public function removeSubscription($subscriptionId)
    {
        $this->client->subscriptions->cancel($subscriptionId);
    }

    /**
     * Update subscription
     *
     * @param  mixed $subscriptionId
     * @return void
     */
    public function updateSubscription($subscription)
    {
        $order = $this->getOrder();

        $data = [
            'items' => [
                [
                    'id' => $subscription->items->first()?->id, // from subscription->items->data[0]->id
                    'price_data' => $this->getPriceData($order),
                ],
            ],
            'metadata' => [
                'order_number' => $order->number,
            ],
        ];

        $this->client->subscriptions->update($subscription->id, $data);
    }
}