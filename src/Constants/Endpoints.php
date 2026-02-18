<?php

declare(strict_types=1);

namespace FoleyBridgeSolutions\MiPaymentChoiceCashier\Constants;

/**
 * Endpoints
 *
 * API endpoint constants for MiPaymentChoice API.
 */
class Endpoints
{
    // Authentication
    public const AUTHENTICATE = '/api/authenticate';

    // Transactions
    public const TRANSACTIONS_BCP = '/api/v2/transactions/bcp';
    /** @deprecated Use TRANSACTIONS_BCP with TransactionType::REFUND instead */
    public const REFUND = '/api/v2/refund';

    // Customers
    public const CUSTOMERS = '/api/customers';

    // Recurring Billing
    public const RECURRING_BILLING_CONTRACTS = '/api/recurringbillingcontracts';

    // QuickPayments
    public const QUICKPAYMENTS_TOKENS = '/api/quickpayments/qp-tokens';
    public const QUICKPAYMENTS_CONVERT_TOKEN = '/api/quickpayments/tokens';

    /**
     * Get merchant-specific token endpoint.
     *
     * @param  int  $merchantKey
     * @return string
     */
    public static function merchantTokens(int $merchantKey): string
    {
        return "/merchants/{$merchantKey}/tokens";
    }

    /**
     * Get merchant-specific card token endpoint.
     *
     * @param  int  $merchantKey
     * @return string
     */
    public static function merchantCardTokens(int $merchantKey): string
    {
        return "/merchants/{$merchantKey}/tokens/cards";
    }

    /**
     * Get specific card token endpoint.
     *
     * @param  int  $merchantKey
     * @param  string  $token
     * @return string
     */
    public static function merchantCardToken(int $merchantKey, string $token): string
    {
        return "/merchants/{$merchantKey}/tokens/cards/{$token}";
    }

    /**
     * Get customer tokens endpoint.
     *
     * @param  int  $merchantKey
     * @param  int  $customerKey
     * @return string
     */
    public static function customerTokens(int $merchantKey, int $customerKey): string
    {
        return "/merchants/{$merchantKey}/customers/{$customerKey}/tokens";
    }

    /**
     * Get merchant contracts endpoint.
     *
     * @param  int  $merchantKey
     * @param  string  $contractId
     * @return string
     */
    public static function merchantContract(int $merchantKey, string $contractId): string
    {
        return "/merchants/{$merchantKey}/contracts/{$contractId}";
    }

    /**
     * Get QuickPayments keys endpoint.
     *
     * @param  int  $merchantKey
     * @return string
     */
    public static function quickPaymentsKeys(int $merchantKey): string
    {
        return "/api/quickpayments/merchants/{$merchantKey}/keys";
    }
}
