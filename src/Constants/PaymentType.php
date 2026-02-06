<?php

declare(strict_types=1);

namespace FoleyBridgeSolutions\MiPaymentChoiceCashier\Constants;

/**
 * Payment type constants for MiPaymentChoice API.
 */
final class PaymentType
{
    public const CARD = 'card';
    public const ACH = 'ach';
    public const CHECK = 'check';

    /**
     * Get all valid payment types.
     *
     * @return array<string>
     */
    public static function all(): array
    {
        return [
            self::CARD,
            self::ACH,
            self::CHECK,
        ];
    }

    /**
     * Check if a payment type is valid.
     *
     * @param  string  $type
     * @return bool
     */
    public static function isValid(string $type): bool
    {
        return in_array($type, self::all(), true);
    }
}
