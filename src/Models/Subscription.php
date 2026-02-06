<?php

declare(strict_types=1);

namespace FoleyBridgeSolutions\MiPaymentChoiceCashier\Models;

use FoleyBridgeSolutions\MiPaymentChoiceCashier\Events\SubscriptionCancelled;
use FoleyBridgeSolutions\MiPaymentChoiceCashier\Exceptions\ApiException;
use FoleyBridgeSolutions\MiPaymentChoiceCashier\Services\ApiClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Subscription extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'subscriptions';

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'trial_ends_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /**
     * Get the user that owns the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        $foreignKey = config('mipaymentchoice.customer_columns.foreign_key', 'user_id');
        return $this->belongsTo(config('mipaymentchoice.model'), $foreignKey);
    }

    /**
     * Determine if the subscription is active.
     *
     * @return bool
     */
    public function active(): bool
    {
        return $this->ends_at === null || $this->onGracePeriod();
    }

    /**
     * Determine if the subscription is within its trial period.
     *
     * @return bool
     */
    public function onTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Determine if the subscription is within its grace period after cancellation.
     *
     * @return bool
     */
    public function onGracePeriod(): bool
    {
        return $this->ends_at && $this->ends_at->isFuture();
    }

    /**
     * Determine if the subscription is cancelled.
     *
     * @return bool
     */
    public function cancelled(): bool
    {
        return $this->ends_at !== null;
    }

    /**
     * Determine if the subscription has ended.
     *
     * @return bool
     */
    public function ended(): bool
    {
        return $this->ends_at && $this->ends_at->isPast();
    }

    /**
     * Cancel the subscription at the end of the billing period.
     *
     * @return $this
     * @throws ApiException
     */
    public function cancel()
    {
        return DB::transaction(function () {
            // Cancel in MPC API if we have a contract ID
            if ($this->mpc_contract_id) {
                $this->cancelMpcContract();
            }

            // Set ends_at using configurable grace period
            $gracePeriodDays = config('mipaymentchoice.subscriptions.grace_period_days', 30);
            $this->ends_at = $this->ends_at ?? now()->addDays($gracePeriodDays);
            $this->save();

            // Dispatch cancellation event
            event(new SubscriptionCancelled($this, false));

            return $this;
        });
    }

    /**
     * Cancel the subscription immediately.
     *
     * @return $this
     * @throws ApiException
     */
    public function cancelNow()
    {
        return DB::transaction(function () {
            // Cancel in MPC API if we have a contract ID
            if ($this->mpc_contract_id) {
                $this->cancelMpcContract();
            }

            $this->ends_at = now();
            $this->save();

            // Dispatch cancellation event (immediate = true)
            event(new SubscriptionCancelled($this, true));

            return $this;
        });
    }

    /**
     * Cancel the recurring billing contract in MPC API.
     *
     * @return void
     * @throws ApiException
     */
    protected function cancelMpcContract(): void
    {
        $api = app(ApiClient::class);
        $merchantKey = $api->getMerchantKey();

        // Cancel the recurring billing contract via API
        $api->delete("/merchants/{$merchantKey}/contracts/{$this->mpc_contract_id}");
    }

    /**
     * Resume a cancelled subscription.
     *
     * @return $this
     * @throws \LogicException If subscription is not within grace period
     * @throws ApiException If API call to resume contract fails
     */
    public function resume()
    {
        if (!$this->onGracePeriod()) {
            throw new \LogicException('Unable to resume subscription that is not within grace period.');
        }

        return DB::transaction(function () {
            // Re-enable the recurring billing contract in MPC API
            if ($this->mpc_contract_id) {
                $this->resumeMpcContract();
            }

            $this->ends_at = null;
            $this->save();

            return $this;
        });
    }

    /**
     * Resume the recurring billing contract in MPC API.
     *
     * @return void
     * @throws ApiException
     */
    protected function resumeMpcContract(): void
    {
        $api = app(ApiClient::class);
        $merchantKey = $api->getMerchantKey();

        // Re-enable the recurring billing contract via API
        $api->put("/merchants/{$merchantKey}/contracts/{$this->mpc_contract_id}", [
            'Status' => 'Active',
        ]);
    }

    /**
     * Get the model's mpc_contract_id attribute.
     *
     * @return string|int|null
     */
    public function getMpcContractIdAttribute(): string|int|null
    {
        return $this->attributes['mpc_contract_id'] ?? null;
    }
}
