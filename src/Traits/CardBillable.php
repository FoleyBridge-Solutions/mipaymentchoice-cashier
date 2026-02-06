<?php

declare(strict_types=1);

namespace FoleyBridgeSolutions\MiPaymentChoiceCashier\Traits;

use FoleyBridgeSolutions\MiPaymentChoiceCashier\Models\PaymentMethod;
use FoleyBridgeSolutions\MiPaymentChoiceCashier\Models\Subscription;
use FoleyBridgeSolutions\MiPaymentChoiceCashier\Services\ApiClient;
use FoleyBridgeSolutions\MiPaymentChoiceCashier\Services\QuickPaymentsService;
use FoleyBridgeSolutions\MiPaymentChoiceCashier\Services\TokenService;
use FoleyBridgeSolutions\MiPaymentChoiceCashier\Constants\TransactionType;
use FoleyBridgeSolutions\MiPaymentChoiceCashier\Events\PaymentSucceeded;
use FoleyBridgeSolutions\MiPaymentChoiceCashier\Events\PaymentFailed;
use FoleyBridgeSolutions\MiPaymentChoiceCashier\Exceptions\ApiException;
use FoleyBridgeSolutions\MiPaymentChoiceCashier\Exceptions\PaymentFailedException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait CardBillable
{
    /**
     * Get the subscriptions for the user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscriptions(): HasMany
    {
        $foreignKey = config('mipaymentchoice.customer_columns.foreign_key', 'user_id');
        return $this->hasMany(Subscription::class, $foreignKey)->orderBy('created_at', 'desc');
    }

    /**
     * Get the payment methods for the user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function paymentMethods(): HasMany
    {
        $foreignKey = config('mipaymentchoice.customer_columns.foreign_key', 'user_id');
        return $this->hasMany(PaymentMethod::class, $foreignKey)->orderBy('created_at', 'desc');
    }

    /**
     * Scope to eager load billing-related data.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithBillingData($query)
    {
        return $query->with(['subscriptions', 'paymentMethods']);
    }

    /**
     * Get the default payment method for the user.
     *
     * @return \FoleyBridgeSolutions\MiPaymentChoiceCashier\Models\PaymentMethod|null
     */
    public function defaultPaymentMethod(): ?PaymentMethod
    {
        return $this->paymentMethods()->where('is_default', true)->first();
    }

    /**
     * Determine if the user has a payment method.
     *
     * @return bool
     */
    public function hasPaymentMethod(): bool
    {
        return $this->paymentMethods()->exists();
    }

    /**
     * Determine if the user has a default payment method.
     *
     * @return bool
     */
    public function hasDefaultPaymentMethod(): bool
    {
        return $this->paymentMethods()->where('is_default', true)->exists();
    }

    /**
     * Get the MiPaymentChoice customer ID.
     *
     * @return string|int|null
     */
    public function mpcCustomerId(): string|int|null
    {
        return $this->{config('mipaymentchoice.customer_columns.customer_id')};
    }

    /**
     * Determine if the user has a MiPaymentChoice customer ID.
     *
     * @return bool
     */
    public function hasMpcCustomerId(): bool
    {
        return !is_null($this->mpcCustomerId());
    }

    /**
     * Create a MiPaymentChoice customer for the user.
     *
     * @param  array  $options
     * @return $this
     * @throws ApiException
     */
    public function createAsMpcCustomer(array $options = [])
    {
        $api = app(ApiClient::class);

        // Get configurable column names
        $nameColumn = config('mipaymentchoice.customer_columns.name', 'name');
        $emailColumn = config('mipaymentchoice.customer_columns.email', 'email');

        $response = $api->post('/api/customers', array_merge([
            'Name' => $this->{$nameColumn} ?? $this->{$emailColumn},
            'Email' => $this->{$emailColumn},
        ], $options));

        if (!isset($response['CustomerId'])) {
            throw new ApiException(
                'Failed to create MiPaymentChoice customer: CustomerId not returned',
                $response
            );
        }

        $this->{config('mipaymentchoice.customer_columns.customer_id')} = $response['CustomerId'];
        $this->save();

        return $this;
    }

    /**
     * Add a payment method to the user.
     *
     * @param  string  $token
     * @param  array  $options
     * @return \FoleyBridgeSolutions\MiPaymentChoiceCashier\Models\PaymentMethod
     * @throws ApiException
     */
    public function addPaymentMethod(string $token, array $options = []): PaymentMethod
    {
        return DB::transaction(function () use ($token, $options) {
            if (!$this->hasMpcCustomerId()) {
                $this->createAsMpcCustomer();
            }

            // Check for existing payment methods inside transaction to avoid race condition
            $hasExistingMethods = $this->paymentMethods()->lockForUpdate()->exists();
            $isDefault = $options['default'] ?? !$hasExistingMethods;

            $paymentMethod = $this->paymentMethods()->create([
                'mpc_token' => $token,
                'type' => $options['type'] ?? 'card',
                'last_four' => $options['last_four'] ?? null,
                'brand' => $options['brand'] ?? null,
                'is_default' => $isDefault,
            ]);

            if ($isDefault && $hasExistingMethods) {
                // Only call makeDefault if there were existing methods to unset
                $paymentMethod->makeDefault();
            }

            return $paymentMethod;
        });
    }

    /**
     * Update the default payment method.
     *
     * @param  string|PaymentMethod  $paymentMethod
     * @return \FoleyBridgeSolutions\MiPaymentChoiceCashier\Models\PaymentMethod
     * @throws \FoleyBridgeSolutions\MiPaymentChoiceCashier\Exceptions\PaymentFailedException
     */
    public function updateDefaultPaymentMethod($paymentMethod): PaymentMethod
    {
        if (is_string($paymentMethod)) {
            $paymentMethod = $this->paymentMethods()->find($paymentMethod);
        }

        if (!$paymentMethod) {
            throw new PaymentFailedException('Payment method not found.');
        }

        return $paymentMethod->makeDefault();
    }

    /**
     * Delete a payment method.
     *
     * Deletes the token from MPC API first, then removes the local record.
     *
     * @param  string|int|PaymentMethod  $paymentMethod  PaymentMethod instance, ID, or token string
     * @return void
     * @throws PaymentFailedException
     */
    public function deletePaymentMethod($paymentMethod): void
    {
        // Resolve the payment method
        if ($paymentMethod instanceof PaymentMethod) {
            $method = $paymentMethod;
        } elseif (is_numeric($paymentMethod)) {
            // Lookup by ID
            $method = $this->paymentMethods()->find($paymentMethod);
        } else {
            // Lookup by token string
            $method = $this->paymentMethods()->where('mpc_token', $paymentMethod)->first();
        }

        if (!$method) {
            return;
        }

        // Delete token from MPC API first
        try {
            $tokenService = app(TokenService::class);
            $tokenService->deleteCardTokens($method->mpc_token);
        } catch (\Exception $e) {
            // Log but don't fail - token may already be deleted or invalid
            Log::warning('Failed to delete MPC token from API', [
                'token' => substr($method->mpc_token, 0, 8) . '...',
                'error' => $e->getMessage(),
            ]);
        }

        // Delete local record
        $method->delete();
    }

    /**
     * Begin creating a new subscription.
     *
     * @param  string  $name
     * @param  string  $plan
     * @return \FoleyBridgeSolutions\MiPaymentChoiceCashier\SubscriptionBuilder
     */
    public function newSubscription(string $name, string $plan)
    {
        return new \FoleyBridgeSolutions\MiPaymentChoiceCashier\SubscriptionBuilder($this, $name, $plan);
    }

    /**
     * Get a subscription by name.
     *
     * @param  string  $name
     * @return \FoleyBridgeSolutions\MiPaymentChoiceCashier\Models\Subscription|null
     */
    public function subscription(string $name = 'default')
    {
        return $this->subscriptions()->where('name', $name)->first();
    }

    /**
     * Determine if the user is on trial.
     *
     * @param  string  $name
     * @param  string|null  $plan
     * @return bool
     */
    public function onTrial(string $name = 'default', $plan = null): bool
    {
        $subscription = $this->subscription($name);

        if (is_null($subscription)) {
            return false;
        }

        if (!is_null($plan) && $subscription->mpc_plan !== $plan) {
            return false;
        }

        return $subscription->onTrial();
    }

    /**
     * Determine if the user has a subscription.
     *
     * @param  string  $name
     * @param  string|null  $plan
     * @return bool
     */
    public function subscribed(string $name = 'default', $plan = null): bool
    {
        $subscription = $this->subscription($name);

        if (is_null($subscription)) {
            return false;
        }

        if (!$subscription->active()) {
            return false;
        }

        if (!is_null($plan) && $subscription->mpc_plan !== $plan) {
            return false;
        }

        return true;
    }

    /**
     * Charge the customer.
     *
     * @param  int  $amount  Amount in cents
     * @param  array  $options  Options including 'payment_method' (string or PaymentMethod), 'idempotency_key'
     * @return array  Gateway response
     * @throws PaymentFailedException  If no valid payment method or charge fails
     */
    public function charge(int $amount, array $options = []): array
    {
        $api = app(ApiClient::class);

        // Get the payment method from options or use default
        $paymentMethod = $options['payment_method'] ?? $this->defaultPaymentMethod();

        // Validate payment method exists
        if (!$paymentMethod) {
            throw new PaymentFailedException('No payment method available for charge.');
        }

        // Extract token based on type with explicit validation
        if ($paymentMethod instanceof PaymentMethod) {
            $token = $paymentMethod->mpc_token;
        } elseif (is_string($paymentMethod)) {
            $token = $paymentMethod;
        } else {
            // Invalid type provided - throw descriptive error
            throw new PaymentFailedException(
                'Invalid payment method type. Expected PaymentMethod instance or token string, got: ' . gettype($paymentMethod)
            );
        }

        // Validate token is not empty
        if (empty($token)) {
            throw new PaymentFailedException('Payment method token is empty or invalid.');
        }

        try {
            $payload = [
                'TransactionType' => TransactionType::SALE,
                'ForceDuplicate' => true,
                'Token' => $token,
                'InvoiceData' => [
                    'TotalAmount' => $amount / 100, // Convert cents to dollars
                ],
            ];

            // Add CustomerID if available
            if ($this->mpcCustomerId()) {
                $payload['CustomerID'] = $this->mpcCustomerId();
            }

            // Add CustomerKey if available
            if (isset($options['customer_key'])) {
                $payload['CustomerKey'] = $options['customer_key'];
            }

            // Add invoice number/description
            if (isset($options['description'])) {
                $payload['InvoiceData']['InvoiceNumber'] = $options['description'];
            }

            if (isset($options['invoice_number'])) {
                $payload['InvoiceData']['InvoiceNumber'] = $options['invoice_number'];
            }

            // Add idempotency key to prevent duplicate charges
            $idempotencyKey = $options['idempotency_key'] ?? Str::uuid()->toString();
            $payload['IdempotencyKey'] = $idempotencyKey;

            $response = $api->post('/api/v2/transactions/bcp', $payload);

            // Dispatch success event (response, billable, amount)
            event(new PaymentSucceeded($response, $this, $amount));

            return $response;
        } catch (\Exception $e) {
            // Dispatch failure event (exception, billable, amount)
            event(new PaymentFailed($e, $this, $amount));

            throw new PaymentFailedException($e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Refund a charge.
     *
     * @param  string  $transactionId
     * @param  int|null  $amount
     * @return array
     */
    public function refund(string $transactionId, $amount = null): array
    {
        $api = app(ApiClient::class);

        $data = [
            'TransactionId' => $transactionId,
        ];

        if ($amount) {
            $data['Amount'] = $amount / 100;
        }

        return $api->post('/api/v2/refund', $data);
    }

    /**
     * Create a QuickPayments token from card details.
     *
     * @param  array  $cardDetails
     * @return string The QuickPayments token
     * @throws PaymentFailedException
     */
    public function createQuickPaymentsToken(array $cardDetails): string
    {
        try {
            $qpService = app(QuickPaymentsService::class);
            $response = $qpService->createQpToken($cardDetails);
            
            $token = $response['QuickPaymentsToken'] ?? null;
            if (!$token) {
                throw new PaymentFailedException('QuickPayments token not returned in response.');
            }
            
            return $token;
        } catch (PaymentFailedException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new PaymentFailedException('Failed to create QuickPayments token: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Charge using a QuickPayments token (one-time use) for card payments.
     *
     * @param  string  $qpToken
     * @param  int  $amount Amount in cents
     * @param  array  $options
     * @return array
     * @throws PaymentFailedException
     */
    public function chargeWithQuickPayments(string $qpToken, int $amount, array $options = []): array
    {
        try {
            $qpService = app(QuickPaymentsService::class);
            return $qpService->charge($qpToken, $amount / 100, $options);
        } catch (\Exception $e) {
            throw new PaymentFailedException('QuickPayments charge failed: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Convert a QuickPayments token to a reusable token and save as payment method.
     *
     * @param  string  $qpToken
     * @param  array  $options
     * @return \FoleyBridgeSolutions\MiPaymentChoiceCashier\Models\PaymentMethod
     * @throws PaymentFailedException
     */
    public function addPaymentMethodFromQuickPayments(string $qpToken, array $options = []): PaymentMethod
    {
        try {
            $qpService = app(QuickPaymentsService::class);
            $response = $qpService->createTokenFromQpToken($qpToken);
            
            $token = $response['Token'] ?? null;
            
            if (!$token) {
                throw new PaymentFailedException('Failed to convert QuickPayments token to reusable token.');
            }

            return $this->addPaymentMethod($token, $options);
        } catch (\Exception $e) {
            throw new PaymentFailedException('Failed to add payment method from QuickPayments: ' . $e->getMessage(), [], 0, $e);
        }
    }

    // ==================== Token Management Methods ====================

    /**
     * Create a card token and optionally save it as a payment method.
     *
     * @param  array  $cardDetails
     * @param  bool  $saveAsPaymentMethod
     * @param  array  $options
     * @return string|PaymentMethod
     * @throws PaymentFailedException
     * @deprecated Use tokenizeCardOnly() for tokens or tokenizeAndSaveCard() for payment methods.
     */
    public function tokenizeCard(array $cardDetails, bool $saveAsPaymentMethod = false, array $options = [])
    {
        try {
            $tokenService = app(TokenService::class);
            $customerKey = $this->mpcCustomerId();
            
            $response = $tokenService->createCardToken($cardDetails, $customerKey);
            $token = $response['Token'] ?? null;

            if (!$token) {
                throw new PaymentFailedException('Failed to create card token.');
            }

            if ($saveAsPaymentMethod) {
                return $this->addPaymentMethod($token, array_merge($options, [
                    'type' => 'card',
                    'last_four' => substr($response['CardNumber'] ?? '', -4),
                    'brand' => $response['CardType'] ?? null,
                ]));
            }

            return $token;
        } catch (\Exception $e) {
            throw new PaymentFailedException('Card tokenization failed: ' . $e->getMessage(), [], 0, $e);
        }
    }



    /**
     * Create a card token without saving as a payment method.
     *
     * @param  array  $cardDetails
     * @return string The token
     * @throws PaymentFailedException
     */
    public function tokenizeCardOnly(array $cardDetails): string
    {
        try {
            $tokenService = app(TokenService::class);
            $customerKey = $this->mpcCustomerId();
            
            $response = $tokenService->createCardToken($cardDetails, $customerKey);
            $token = $response['Token'] ?? null;

            if (!$token) {
                throw new PaymentFailedException('Failed to create card token.');
            }

            return $token;
        } catch (PaymentFailedException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new PaymentFailedException('Card tokenization failed: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Create a card token and save it as a payment method.
     *
     * @param  array  $cardDetails
     * @param  array  $options
     * @return \FoleyBridgeSolutions\MiPaymentChoiceCashier\Models\PaymentMethod
     * @throws PaymentFailedException
     */
    public function tokenizeAndSaveCard(array $cardDetails, array $options = []): PaymentMethod
    {
        try {
            $tokenService = app(TokenService::class);
            $customerKey = $this->mpcCustomerId();
            
            $response = $tokenService->createCardToken($cardDetails, $customerKey);
            $token = $response['Token'] ?? null;

            if (!$token) {
                throw new PaymentFailedException('Failed to create card token.');
            }

            return $this->addPaymentMethod($token, array_merge($options, [
                'type' => 'card',
                'last_four' => substr($response['CardNumber'] ?? '', -4),
                'brand' => $response['CardType'] ?? null,
            ]));
        } catch (PaymentFailedException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new PaymentFailedException('Card tokenization failed: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Get all tokens associated with this customer.
     *
     * @return array
     * @throws PaymentFailedException
     */
    public function getTokens(): array
    {
        if (!$this->hasMpcCustomerId()) {
            return [];
        }

        try {
            $tokenService = app(TokenService::class);
            // Cast to int to satisfy TokenService::getCustomerTokens() signature
            return $tokenService->getCustomerTokens((int) $this->mpcCustomerId());
        } catch (\Exception $e) {
            throw new PaymentFailedException('Failed to retrieve tokens: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Update a card token.
     *
     * @param  string  $token
     * @param  array  $updates
     * @return array
     * @throws PaymentFailedException
     */
    public function updateCardToken(string $token, array $updates): array
    {
        try {
            $tokenService = app(TokenService::class);
            return $tokenService->updateCardToken($token, $updates);
        } catch (\Exception $e) {
            throw new PaymentFailedException('Failed to update card token: ' . $e->getMessage(), [], 0, $e);
        }
    }



    /**
     * Delete a card token.
     *
     * @param  string  $token
     * @return void
     * @throws PaymentFailedException
     */
    public function deleteCardToken(string $token): void
    {
        try {
            $tokenService = app(TokenService::class);
            $tokenService->deleteCardTokens($token);
        } catch (\Exception $e) {
            throw new PaymentFailedException('Failed to delete card token: ' . $e->getMessage(), [], 0, $e);
        }
    }



    /**
     * Create a token from a transaction reference (PnRef).
     *
     * @param  int  $pnRef
     * @return array Returns CardToken and/or CheckToken
     * @throws PaymentFailedException
     */
    public function tokenizeFromTransaction(int $pnRef): array
    {
        try {
            $tokenService = app(TokenService::class);
            $customerKey = $this->mpcCustomerId();
            
            return $tokenService->createTokenFromPnRef($pnRef, $customerKey);
        } catch (\Exception $e) {
            throw new PaymentFailedException('Failed to create token from transaction: ' . $e->getMessage(), [], 0, $e);
        }
    }
}
