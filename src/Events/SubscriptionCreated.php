<?php

declare(strict_types=1);

namespace FoleyBridgeSolutions\MiPaymentChoiceCashier\Events;

use FoleyBridgeSolutions\MiPaymentChoiceCashier\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * SubscriptionCreated
 *
 * Dispatched when a new subscription is created.
 */
class SubscriptionCreated
{
    use Dispatchable, SerializesModels;

    /**
     * The subscription that was created.
     *
     * @var \FoleyBridgeSolutions\MiPaymentChoiceCashier\Models\Subscription
     */
    public Subscription $subscription;

    /**
     * Create a new event instance.
     *
     * @param  \FoleyBridgeSolutions\MiPaymentChoiceCashier\Models\Subscription  $subscription
     * @return void
     */
    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }
}
