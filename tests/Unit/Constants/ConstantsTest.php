<?php

declare(strict_types=1);

namespace FoleyBridgeSolutions\MiPaymentChoiceCashier\Tests\Unit\Constants;

use PHPUnit\Framework\TestCase;
use FoleyBridgeSolutions\MiPaymentChoiceCashier\Constants\TransactionType;
use FoleyBridgeSolutions\MiPaymentChoiceCashier\Constants\TokenFormat;
use FoleyBridgeSolutions\MiPaymentChoiceCashier\Constants\PaymentType;
use FoleyBridgeSolutions\MiPaymentChoiceCashier\Constants\Frequency;

class ConstantsTest extends TestCase
{
    public function test_transaction_type_constants_exist(): void
    {
        $this->assertEquals('Sale', TransactionType::SALE);
        $this->assertEquals('Auth', TransactionType::AUTH);
        $this->assertEquals('Capture', TransactionType::CAPTURE);
        $this->assertEquals('Void', TransactionType::VOID);
        $this->assertEquals('Refund', TransactionType::REFUND);
        $this->assertEquals('Credit', TransactionType::CREDIT);
        $this->assertEquals('Force', TransactionType::FORCE);
        $this->assertEquals('BalanceInquiry', TransactionType::BALANCE_INQUIRY);
    }

    public function test_transaction_type_all_returns_array(): void
    {
        $all = TransactionType::all();
        $this->assertIsArray($all);
        $this->assertContains('Sale', $all);
        $this->assertContains('Refund', $all);
    }

    public function test_transaction_type_is_valid(): void
    {
        $this->assertTrue(TransactionType::isValid('Sale'));
        $this->assertTrue(TransactionType::isValid('Refund'));
        $this->assertFalse(TransactionType::isValid('InvalidType'));
        $this->assertFalse(TransactionType::isValid('sale')); // Case sensitive
    }

    public function test_token_format_constants_exist(): void
    {
        $this->assertEquals('Uid', TokenFormat::UID);
        $this->assertEquals('Numeric', TokenFormat::NUMERIC);
        $this->assertEquals('Alphanumeric', TokenFormat::ALPHANUMERIC);
    }

    public function test_token_format_all_returns_array(): void
    {
        $all = TokenFormat::all();
        $this->assertIsArray($all);
        $this->assertCount(3, $all);
    }

    public function test_token_format_is_valid(): void
    {
        $this->assertTrue(TokenFormat::isValid('Uid'));
        $this->assertTrue(TokenFormat::isValid('Numeric'));
        $this->assertTrue(TokenFormat::isValid('Alphanumeric'));
        $this->assertFalse(TokenFormat::isValid('Invalid'));
    }

    public function test_payment_type_constants_exist(): void
    {
        $this->assertEquals('card', PaymentType::CARD);
        $this->assertEquals('ach', PaymentType::ACH);
        $this->assertEquals('check', PaymentType::CHECK);
    }

    public function test_payment_type_is_valid(): void
    {
        $this->assertTrue(PaymentType::isValid('card'));
        $this->assertTrue(PaymentType::isValid('ach'));
        $this->assertFalse(PaymentType::isValid('bitcoin'));
    }

    public function test_frequency_constants_exist(): void
    {
        $this->assertEquals('Daily', Frequency::DAILY);
        $this->assertEquals('Weekly', Frequency::WEEKLY);
        $this->assertEquals('BiWeekly', Frequency::BI_WEEKLY);
        $this->assertEquals('Monthly', Frequency::MONTHLY);
        $this->assertEquals('Quarterly', Frequency::QUARTERLY);
        $this->assertEquals('SemiAnnually', Frequency::SEMI_ANNUALLY);
        $this->assertEquals('Annually', Frequency::ANNUALLY);
    }

    public function test_frequency_is_valid(): void
    {
        $this->assertTrue(Frequency::isValid('Monthly'));
        $this->assertTrue(Frequency::isValid('Annually'));
        $this->assertFalse(Frequency::isValid('Every5Years'));
    }
}
