<?php

declare(strict_types=1);

namespace FoleyBridgeSolutions\MiPaymentChoiceCashier\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class PaymentMethod extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'payment_methods';

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * Get the casts array, conditionally adding encryption for mpc_token.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        $casts = [
            'is_default' => 'boolean',
        ];

        // Add encryption cast for mpc_token if enabled in config
        if (config('mipaymentchoice.encrypt_tokens', false)) {
            $casts['mpc_token'] = 'encrypted';
        }

        return $casts;
    }

    /**
     * Get the user that owns the payment method.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        $foreignKey = config('mipaymentchoice.customer_columns.foreign_key', 'user_id');
        return $this->belongsTo(config('mipaymentchoice.model'), $foreignKey);
    }

    /**
     * Mark this payment method as the default.
     *
     * @return $this
     * @throws \RuntimeException If user relationship is not loaded
     */
    public function makeDefault(): static
    {
        return DB::transaction(function () {
            // Guard: Ensure user relationship exists
            if (!$this->user) {
                throw new \RuntimeException(
                    'Cannot make payment method default: user relationship not found'
                );
            }

            // Unset all other payment methods as default
            $this->user->paymentMethods()->update(['is_default' => false]);

            // Set this one as default
            $this->is_default = true;
            $this->save();

            return $this;
        });
    }
}
