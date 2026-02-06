<?php

declare(strict_types=1);

namespace FoleyBridgeSolutions\MiPaymentChoiceCashier\Constants;

/**
 * Transaction type constants for MiPaymentChoice API.
 */
final class TransactionType
{
    public const SALE = 'Sale';
    public const AUTH = 'Auth';
    public const CAPTURE = 'Capture';
    public const VOID = 'Void';
    public const REFUND = 'Refund';
    public const CREDIT = 'Credit';
    public const FORCE = 'Force';
    public const BALANCE_INQUIRY = 'BalanceInquiry';

    /**
     * Get all valid transaction types.
     *
     * @return array<string>
     */
    public static function all(): array
    {
        return [
            self::SALE,
            self::AUTH,
            self::CAPTURE,
            self::VOID,
            self::REFUND,
            self::CREDIT,
            self::FORCE,
            self::BALANCE_INQUIRY,
        ];
    }

    /**
     * Check if a transaction type is valid.
     *
     * @param  string  $type
     * @return bool
     */
    public static function isValid(string $type): bool
    {
        return in_array($type, self::all(), true);
    }
}
