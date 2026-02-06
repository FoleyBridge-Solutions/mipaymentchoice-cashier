<?php

declare(strict_types=1);

namespace FoleyBridgeSolutions\MiPaymentChoiceCashier\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * PaymentSucceeded
 *
 * Dispatched when a payment is successfully processed.
 */
class PaymentSucceeded
{
    use Dispatchable, SerializesModels;

    /**
     * The payment response from the gateway.
     *
     * @var array
     */
    public array $response;

    /**
     * The billable model that made the payment.
     *
     * @var mixed
     */
    public $billable;

    /**
     * The amount charged in cents.
     *
     * @var int
     */
    public int $amount;

    /**
     * Create a new event instance.
     *
     * @param  array  $response
     * @param  mixed  $billable
     * @param  int  $amount
     * @return void
     */
    public function __construct(array $response, $billable, int $amount)
    {
        $this->response = $response;
        $this->billable = $billable;
        $this->amount = $amount;
    }
}
