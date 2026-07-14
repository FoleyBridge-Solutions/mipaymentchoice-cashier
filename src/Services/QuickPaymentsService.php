<?php

declare(strict_types=1);

namespace FoleyBridgeSolutions\MiPaymentChoiceCashier\Services;

use FoleyBridgeSolutions\MiPaymentChoiceCashier\Exceptions\ApiException;
use FoleyBridgeSolutions\MiPaymentChoiceCashier\Validation\CardValidator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class QuickPaymentsService
{
    /**
     * The API client instance.
     *
     * @var \FoleyBridgeSolutions\MiPaymentChoiceCashier\Services\ApiClient
     */
    protected $api;

    /**
     * The QuickPayments key (optional, will be fetched if not provided).
     *
     * @var string|null
     */
    protected $quickPaymentsKey;

    /**
     * Create a new QuickPayments service instance.
     *
     * @param  string|null  $quickPaymentsKey
     * @return void
     */
    public function __construct(ApiClient $api, $quickPaymentsKey = null)
    {
        $this->api = $api;
        $this->quickPaymentsKey = $quickPaymentsKey;
    }

    /**
     * Get the merchant key from the API client.
     */
    protected function getMerchantKeyFromToken(): int
    {
        return $this->api->getMerchantKey();
    }

    /**
     * Get the QuickPayments key for creating tokens.
     *
     * @throws ApiException
     */
    protected function getQuickPaymentsKey(): string
    {
        // Use configured key if available
        if ($this->quickPaymentsKey) {
            return $this->quickPaymentsKey;
        }

        // Cache the QuickPayments key (24 hours)
        return Cache::remember('mipaymentchoice_quickpayments_key', 86400, function () {
            $merchantKey = $this->getMerchantKeyFromToken();

            // Try to get existing key
            $response = $this->fetchMerchantQuickPaymentsKey($merchantKey);

            if (! empty($response['QuickPaymentsKey'])) {
                return $response['QuickPaymentsKey'];
            }

            // Create new key if none exists
            $response = $this->createMerchantQuickPaymentsKey($merchantKey);

            if (! empty($response['QuickPaymentsKey'])) {
                return $response['QuickPaymentsKey'];
            }

            throw new ApiException('Failed to get or create QuickPayments key');
        });
    }

    /**
     * Fetch the merchant's QuickPayments key.
     *
     * @throws ApiException
     */
    protected function fetchMerchantQuickPaymentsKey(int $merchantKey): array
    {
        // Fixed: Removed duplicate slash in URL path
        return $this->api->get("/api/quickpayments/merchants/{$merchantKey}/keys");
    }

    /**
     * Create a new QuickPayments key for the merchant.
     *
     * @throws ApiException
     */
    protected function createMerchantQuickPaymentsKey(int $merchantKey): array
    {
        // Fixed: Removed duplicate slash in URL path
        return $this->api->post("/api/quickpayments/merchants/{$merchantKey}/keys", [
            'MerchantKey' => $merchantKey,
        ]);
    }

    /**
     * Create a one-time use QuickPayments token from card data.
     *
     * @return array Returns array with 'QuickPaymentsToken'
     *
     * @throws ApiException
     * @throws \InvalidArgumentException
     */
    public function createQpToken(array $cardDetails, ?string $quickPaymentsKey = null): array
    {
        CardValidator::validate($cardDetails);

        if (! $quickPaymentsKey) {
            $quickPaymentsKey = $this->getQuickPaymentsKey();
        }

        $cardData = [
            'CardNumber' => $cardDetails['number'],
            'ExpirationDate' => sprintf(
                '%02d%02d',
                $cardDetails['exp_month'],
                substr((string) $cardDetails['exp_year'], -2)
            ),
        ];

        if (isset($cardDetails['cvc'])) {
            $cardData['Cvv'] = (string) $cardDetails['cvc'];
        }

        if (isset($cardDetails['name'])) {
            $cardData['NameOnCard'] = $cardDetails['name'];
        }

        if (isset($cardDetails['street'])) {
            $cardData['Street'] = $cardDetails['street'];
        }

        if (isset($cardDetails['zip_code'])) {
            $cardData['ZipCode'] = $cardDetails['zip_code'];
        }

        if (isset($cardDetails['phone'])) {
            $cardData['Phone'] = $cardDetails['phone'];
        }

        if (isset($cardDetails['email'])) {
            $cardData['Email'] = $cardDetails['email'];
        }

        if (isset($cardDetails['entry_mode'])) {
            $cardData['EntryMode'] = $cardDetails['entry_mode'];
        }

        $payload = [
            'QuickPaymentsKey' => $quickPaymentsKey,
            'CardData' => $cardData,
        ];

        // Fixed: Removed duplicate slash in URL path
        return $this->api->post('/api/quickpayments/qp-tokens', $payload);
    }

    /**
     * Create a reusable token from a QuickPayments token.
     *
     * @param  string  $tokenFormat  Format: 'Uid', 'Numeric', or 'Alphanumeric'
     * @return array Returns array with 'Token'
     *
     * @throws ApiException
     */
    public function createTokenFromQpToken(string $qpToken, ?string $quickPaymentsKey = null, string $tokenFormat = 'Uid'): array
    {
        if (! $quickPaymentsKey) {
            $quickPaymentsKey = $this->getQuickPaymentsKey();
        }

        // Fixed: Removed duplicate slash in URL path
        return $this->api->post('/api/quickpayments/tokens', [
            'QuickPaymentsKey' => $quickPaymentsKey,
            'QuickPaymentsToken' => $qpToken,
            'TokenFormat' => $tokenFormat,
        ]);
    }

    /**
     * Process a quick payment using a QP token (card payments).
     *
     * @throws ApiException
     */
    public function charge(string $qpToken, float $amount, array $options = []): array
    {
        $payload = [
            'TransactionType' => 'Sale',
            'ForceDuplicate' => true,
            'Token' => $qpToken,
            'InvoiceData' => [
                'TotalAmount' => $amount,
            ],
        ];

        if (isset($options['description'])) {
            $payload['InvoiceData']['InvoiceNumber'] = $options['description'];
        }

        if (isset($options['invoice_number'])) {
            $payload['InvoiceData']['InvoiceNumber'] = $options['invoice_number'];
        }

        // Add idempotency key to prevent duplicate charges (mirrors
        // CardBillable::charge). Callers that don't supply one keep the
        // previous behavior of a unique key per call.
        $payload['IdempotencyKey'] = $options['idempotency_key'] ?? Str::uuid()->toString();

        return $this->api->post('/api/v2/transactions/bcp', $payload);
    }
}
