<?php

declare(strict_types=1);

namespace FoleyBridgeSolutions\MiPaymentChoiceCashier\Constants;

/**
 * Token format constants for MiPaymentChoice API.
 */
final class TokenFormat
{
    public const UID = 'Uid';
    public const NUMERIC = 'Numeric';
    public const ALPHANUMERIC = 'Alphanumeric';

    /**
     * Get all valid token formats.
     *
     * @return array<string>
     */
    public static function all(): array
    {
        return [
            self::UID,
            self::NUMERIC,
            self::ALPHANUMERIC,
        ];
    }

    /**
     * Check if a token format is valid.
     *
     * @param  string  $format
     * @return bool
     */
    public static function isValid(string $format): bool
    {
        return in_array($format, self::all(), true);
    }
}
