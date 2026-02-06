<?php

declare(strict_types=1);

namespace FoleyBridgeSolutions\MiPaymentChoiceCashier\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use FoleyBridgeSolutions\MiPaymentChoiceCashier\Validation\CardValidator;
use InvalidArgumentException;

class CardValidationTest extends TestCase
{
    public function test_valid_card_details_pass_validation(): void
    {
        // Valid card - should not throw
        $cardDetails = [
            'number' => '4111111111111111',
            'exp_month' => 12,
            'exp_year' => date('Y') + 2,
        ];

        CardValidator::validate($cardDetails);
        $this->assertTrue(true); // If we get here, validation passed
    }

    public function test_missing_card_number_throws_exception(): void
    {
        $cardDetails = [
            'exp_month' => 12,
            'exp_year' => date('Y') + 2,
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required card details: number');

        CardValidator::validate($cardDetails);
    }

    public function test_missing_expiration_month_throws_exception(): void
    {
        $cardDetails = [
            'number' => '4111111111111111',
            'exp_year' => date('Y') + 2,
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required card details: exp_month');

        CardValidator::validate($cardDetails);
    }

    public function test_missing_expiration_year_throws_exception(): void
    {
        $cardDetails = [
            'number' => '4111111111111111',
            'exp_month' => 12,
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required card details: exp_year');

        CardValidator::validate($cardDetails);
    }

    public function test_multiple_missing_fields_listed_in_exception(): void
    {
        $cardDetails = [];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required card details: number, exp_month, exp_year');

        CardValidator::validate($cardDetails);
    }

    public function test_invalid_card_number_too_short_throws_exception(): void
    {
        $cardDetails = [
            'number' => '411111', // Too short
            'exp_month' => 12,
            'exp_year' => date('Y') + 2,
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid card number: must be 13-19 digits');

        CardValidator::validate($cardDetails);
    }

    public function test_invalid_card_number_too_long_throws_exception(): void
    {
        $cardDetails = [
            'number' => '41111111111111111111', // 20 digits - too long
            'exp_month' => 12,
            'exp_year' => date('Y') + 2,
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid card number: must be 13-19 digits');

        CardValidator::validate($cardDetails);
    }

    public function test_card_number_with_spaces_is_normalized(): void
    {
        // Card number with spaces should be valid (spaces stripped)
        $cardDetails = [
            'number' => '4111 1111 1111 1111',
            'exp_month' => 12,
            'exp_year' => date('Y') + 2,
        ];

        CardValidator::validate($cardDetails);
        $this->assertTrue(true); // If we get here, validation passed
    }

    public function test_card_number_with_dashes_is_normalized(): void
    {
        // Card number with dashes should be valid (dashes stripped)
        $cardDetails = [
            'number' => '4111-1111-1111-1111',
            'exp_month' => 12,
            'exp_year' => date('Y') + 2,
        ];

        CardValidator::validate($cardDetails);
        $this->assertTrue(true); // If we get here, validation passed
    }

    public function test_invalid_expiration_month_zero_throws_exception(): void
    {
        $cardDetails = [
            'number' => '4111111111111111',
            'exp_month' => 0,
            'exp_year' => date('Y') + 2,
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid expiration month: must be 1-12');

        CardValidator::validate($cardDetails);
    }

    public function test_invalid_expiration_month_13_throws_exception(): void
    {
        $cardDetails = [
            'number' => '4111111111111111',
            'exp_month' => 13,
            'exp_year' => date('Y') + 2,
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid expiration month: must be 1-12');

        CardValidator::validate($cardDetails);
    }

    public function test_expired_card_throws_exception(): void
    {
        $cardDetails = [
            'number' => '4111111111111111',
            'exp_month' => 1,
            'exp_year' => 2020, // Past year
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Card has expired');

        CardValidator::validate($cardDetails);
    }

    public function test_two_digit_year_is_normalized(): void
    {
        // 2-digit year should work (e.g., 28 -> 2028)
        $twoDigitYear = (int) date('y') + 3; // 3 years from now
        $cardDetails = [
            'number' => '4111111111111111',
            'exp_month' => 12,
            'exp_year' => $twoDigitYear,
        ];

        CardValidator::validate($cardDetails);
        $this->assertTrue(true); // If we get here, validation passed
    }

    public function test_empty_card_number_throws_exception(): void
    {
        $cardDetails = [
            'number' => '',
            'exp_month' => 12,
            'exp_year' => date('Y') + 2,
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required card details: number');

        CardValidator::validate($cardDetails);
    }

    public function test_valid_13_digit_card_number(): void
    {
        // 13-digit card (minimum valid length) - use a Luhn-valid number
        $cardDetails = [
            'number' => '4222222222222', // Valid 13-digit Visa test number
            'exp_month' => 12,
            'exp_year' => date('Y') + 2,
        ];

        CardValidator::validate($cardDetails);
        $this->assertTrue(true); // If we get here, validation passed
    }

    public function test_valid_19_digit_card_number(): void
    {
        // 19-digit card (maximum valid length) - Luhn-valid number
        $cardDetails = [
            'number' => '6304000000000000000',
            'exp_month' => 12,
            'exp_year' => date('Y') + 2,
        ];

        CardValidator::validate($cardDetails);
        $this->assertTrue(true); // If we get here, validation passed
    }

    public function test_luhn_invalid_card_fails(): void
    {
        // Card number that fails Luhn check
        $cardDetails = [
            'number' => '4111111111111112', // Invalid Luhn
            'exp_month' => 12,
            'exp_year' => date('Y') + 2,
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid card number: failed checksum validation');

        CardValidator::validate($cardDetails);
    }

    public function test_cvv_validation_valid_3_digits(): void
    {
        // Valid 3-digit CVV
        CardValidator::validateCvv('123');
        $this->assertTrue(true);
    }

    public function test_cvv_validation_valid_4_digits(): void
    {
        // Valid 4-digit CVV (Amex)
        CardValidator::validateCvv('1234');
        $this->assertTrue(true);
    }

    public function test_cvv_validation_invalid_2_digits(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid CVV: must be 3-4 digits');

        CardValidator::validateCvv('12');
    }

    public function test_cvv_validation_invalid_5_digits(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid CVV: must be 3-4 digits');

        CardValidator::validateCvv('12345');
    }

    public function test_detect_visa_brand(): void
    {
        $brand = CardValidator::detectBrand('4111111111111111');
        $this->assertEquals('visa', $brand);
    }

    public function test_detect_mastercard_brand(): void
    {
        $brand = CardValidator::detectBrand('5500000000000004');
        $this->assertEquals('mastercard', $brand);
    }

    public function test_detect_amex_brand(): void
    {
        $brand = CardValidator::detectBrand('340000000000009');
        $this->assertEquals('amex', $brand);
    }

    public function test_detect_discover_brand(): void
    {
        $brand = CardValidator::detectBrand('6011000000000004');
        $this->assertEquals('discover', $brand);
    }

    public function test_detect_unknown_brand(): void
    {
        $brand = CardValidator::detectBrand('9999999999999999');
        $this->assertNull($brand);
    }
}
