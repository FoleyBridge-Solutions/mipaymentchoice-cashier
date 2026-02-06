<?php

declare(strict_types=1);

namespace FoleyBridgeSolutions\MiPaymentChoiceCashier\Validation;

/**
 * CardValidator
 *
 * Validates credit/debit card details for tokenization and payment operations.
 */
class CardValidator
{
    /**
     * Validate required card details.
     *
     * @param  array  $cardDetails  Card details to validate
     * @return void
     * @throws \InvalidArgumentException  If validation fails
     */
    public static function validate(array $cardDetails): void
    {
        self::validateRequiredFields($cardDetails);
        self::validateCardNumber($cardDetails['number']);
        self::validateExpiration($cardDetails['exp_month'], $cardDetails['exp_year']);
    }

    /**
     * Validate required fields are present.
     *
     * @param  array  $cardDetails
     * @return void
     * @throws \InvalidArgumentException
     */
    protected static function validateRequiredFields(array $cardDetails): void
    {
        $required = ['number', 'exp_month', 'exp_year'];
        $missing = [];

        foreach ($required as $field) {
            if (!isset($cardDetails[$field]) || $cardDetails[$field] === '') {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            throw new \InvalidArgumentException(
                'Missing required card details: ' . implode(', ', $missing)
            );
        }
    }

    /**
     * Validate card number format.
     *
     * @param  string|int  $number
     * @return void
     * @throws \InvalidArgumentException
     */
    protected static function validateCardNumber($number): void
    {
        // Remove non-digits
        $cardNumber = preg_replace('/\D/', '', (string) $number);
        
        if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
            throw new \InvalidArgumentException(
                'Invalid card number: must be 13-19 digits'
            );
        }

        // Luhn algorithm check
        if (!self::luhnCheck($cardNumber)) {
            throw new \InvalidArgumentException(
                'Invalid card number: failed checksum validation'
            );
        }
    }

    /**
     * Validate expiration date.
     *
     * @param  int|string  $month
     * @param  int|string  $year
     * @return void
     * @throws \InvalidArgumentException
     */
    protected static function validateExpiration($month, $year): void
    {
        $expMonth = (int) $month;
        $expYear = (int) $year;
        $currentYear = (int) date('Y');
        $currentMonth = (int) date('n');

        // Validate month range
        if ($expMonth < 1 || $expMonth > 12) {
            throw new \InvalidArgumentException(
                'Invalid expiration month: must be 1-12'
            );
        }

        // Normalize to 4-digit year
        if ($expYear < 100) {
            $expYear += 2000;
        }

        // Check if expired
        if ($expYear < $currentYear || ($expYear === $currentYear && $expMonth < $currentMonth)) {
            throw new \InvalidArgumentException(
                'Card has expired'
            );
        }
    }

    /**
     * Validate CVV format.
     *
     * @param  string|int  $cvv
     * @return void
     * @throws \InvalidArgumentException
     */
    public static function validateCvv($cvv): void
    {
        $cvvString = (string) $cvv;
        
        // CVV should be 3-4 digits
        if (!preg_match('/^\d{3,4}$/', $cvvString)) {
            throw new \InvalidArgumentException(
                'Invalid CVV: must be 3-4 digits'
            );
        }
    }

    /**
     * Perform Luhn algorithm check on card number.
     *
     * @param  string  $number
     * @return bool
     */
    protected static function luhnCheck(string $number): bool
    {
        $sum = 0;
        $length = strlen($number);
        $parity = $length % 2;

        for ($i = 0; $i < $length; $i++) {
            $digit = (int) $number[$i];
            
            if ($i % 2 === $parity) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            
            $sum += $digit;
        }

        return $sum % 10 === 0;
    }

    /**
     * Get the card brand based on card number.
     *
     * @param  string|int  $number
     * @return string|null
     */
    public static function detectBrand($number): ?string
    {
        $cardNumber = preg_replace('/\D/', '', (string) $number);

        $patterns = [
            'visa' => '/^4/',
            'mastercard' => '/^(5[1-5]|2[2-7])/',
            'amex' => '/^3[47]/',
            'discover' => '/^6(?:011|5)/',
            'diners' => '/^3(?:0[0-5]|[68])/',
            'jcb' => '/^35/',
        ];

        foreach ($patterns as $brand => $pattern) {
            if (preg_match($pattern, $cardNumber)) {
                return $brand;
            }
        }

        return null;
    }
}
