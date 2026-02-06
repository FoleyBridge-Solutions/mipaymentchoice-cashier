<?php

declare(strict_types=1);

namespace FoleyBridgeSolutions\MiPaymentChoiceCashier\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * PaymentFailed
 *
 * Dispatched when a payment fails.
 */
class PaymentFailed
{
    use Dispatchable, SerializesModels;

    /**
     * The exception that caused the failure.
     *
     * @var \Throwable
     */
    public \Throwable $exception;

    /**
     * The billable model that attempted the payment.
     *
     * @var mixed
     */
    public $billable;

    /**
     * The amount that was attempted in cents.
     *
     * @var int
     */
    public int $amount;

    /**
     * Create a new event instance.
     *
     * @param  \Throwable  $exception
     * @param  mixed  $billable
     * @param  int  $amount
     * @return void
     */
    public function __construct(\Throwable $exception, $billable, int $amount)
    {
        $this->exception = $exception;
        $this->billable = $billable;
        $this->amount = $amount;
    }
}
