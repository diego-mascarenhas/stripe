<?php

namespace App\Services\Stripe;

use Stripe\StripeClient;

class StripeSubscriptionService
{
    public function __construct(private readonly StripeClient $client)
    {
    }

    /**
     * @return \Generator<\Stripe\Subscription>
     */
    public function subscriptions(array $params = []): \Generator
    {
        $params = array_merge([
            'limit' => 100,
            'status' => 'all',
            'expand' => [
                'data.customer',
                'data.latest_invoice',
                'data.items.data.price',
            ],
        ], $params);

        $collection = $this->client->subscriptions->all($params);

        foreach ($collection->autoPagingIterator() as $subscription) {
            yield $subscription;
        }
    }
}

