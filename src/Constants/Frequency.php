<?php

declare(strict_types=1);

namespace FoleyBridgeSolutions\MiPaymentChoiceCashier\Constants;

/**
 * Frequency constants for recurring billing in MiPaymentChoice API.
 */
final class Frequency
{
    public const DAILY = 'Daily';
    public const WEEKLY = 'Weekly';
    public const BI_WEEKLY = 'BiWeekly';
    public const MONTHLY = 'Monthly';
    public const BI_MONTHLY = 'BiMonthly';
    public const QUARTERLY = 'Quarterly';
    public const SEMI_ANNUALLY = 'SemiAnnually';
    public const ANNUALLY = 'Annually';

    /**
     * Get all valid frequencies.
     *
     * @return array<string>
     */
    public static function all(): array
    {
        return [
            self::DAILY,
            self::WEEKLY,
            self::BI_WEEKLY,
            self::MONTHLY,
            self::BI_MONTHLY,
            self::QUARTERLY,
            self::SEMI_ANNUALLY,
            self::ANNUALLY,
        ];
    }

    /**
     * Check if a frequency is valid.
     *
     * @param  string  $frequency
     * @return bool
     */
    public static function isValid(string $frequency): bool
    {
        return in_array($frequency, self::all(), true);
    }
}
