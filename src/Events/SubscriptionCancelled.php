<?php

declare(strict_types=1);

namespace FoleyBridgeSolutions\MiPaymentChoiceCashier\Events;

use FoleyBridgeSolutions\MiPaymentChoiceCashier\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * SubscriptionCancelled
 *
 * Dispatched when a subscription is cancelled.
 */
class SubscriptionCancelled
{
    use Dispatchable, SerializesModels;

    /**
     * The subscription that was cancelled.
     *
     * @var \FoleyBridgeSolutions\MiPaymentChoiceCashier\Models\Subscription
     */
    public Subscription $subscription;

    /**
     * Whether the cancellation was immediate.
     *
     * @var bool
     */
    public bool $immediate;

    /**
     * Create a new event instance.
     *
     * @param  \FoleyBridgeSolutions\MiPaymentChoiceCashier\Models\Subscription  $subscription
     * @param  bool  $immediate
     * @return void
     */
    public function __construct(Subscription $subscription, bool $immediate = false)
    {
        $this->subscription = $subscription;
        $this->immediate = $immediate;
    }
}
