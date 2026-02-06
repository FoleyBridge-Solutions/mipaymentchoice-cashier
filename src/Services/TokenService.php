<?php

declare(strict_types=1);

namespace FoleyBridgeSolutions\MiPaymentChoiceCashier\Services;

use FoleyBridgeSolutions\MiPaymentChoiceCashier\Exceptions\ApiException;
use FoleyBridgeSolutions\MiPaymentChoiceCashier\Validation\CardValidator;

class TokenService
{
    /**
     * The API client instance.
     *
     * @var \FoleyBridgeSolutions\MiPaymentChoiceCashier\Services\ApiClient
     */
    protected $api;

    /**
     * Create a new token service instance.
     *
     * @return void
     */
    public function __construct(ApiClient $api)
    {
        $this->api = $api;
    }

    /**
     * Get the merchant key from the JWT token.
     */
    protected function getMerchantKey(): int
    {
        return $this->api->getMerchantKey();
    }

    // ==================== Card Token Methods ====================

    /**
     * Create a card token.
     *
     * @param  array  $cardDetails  Card details (number, exp_month, exp_year, name, street, postal_code)
     * @param  int|null  $customerKey  Optional customer key to associate token with
     * @param  string  $tokenFormat  Token format: 'Uid', 'Numeric', or 'Alphanumeric'
     * @return array Response containing Token
     *
     * @throws ApiException
     * @throws \InvalidArgumentException
     */
    public function createCardToken(array $cardDetails, ?int $customerKey = null, string $tokenFormat = 'Uid'): array
    {
        CardValidator::validate($cardDetails);

        // Cache merchant key locally to avoid duplicate API calls
        $merchantKey = $this->getMerchantKey();

        $payload = [
            'MerchantKey' => $merchantKey,
            'CardNumber' => $cardDetails['number'],
            'ExpirationDate' => sprintf(
                '%02d%02d',
                $cardDetails['exp_month'],
                substr((string) $cardDetails['exp_year'], -2)
            ),
            'TokenFormat' => $tokenFormat,
        ];

        if ($customerKey) {
            $payload['CustomerKey'] = $customerKey;
        }

        if (isset($cardDetails['name'])) {
            $payload['NameOnCard'] = $cardDetails['name'];
        }

        if (isset($cardDetails['street'])) {
            $payload['StreetAddress'] = $cardDetails['street'];
        }

        if (isset($cardDetails['postal_code'])) {
            $payload['PostalCode'] = $cardDetails['postal_code'];
        }

        // Use cached merchant key in URL
        return $this->api->post("/merchants/{$merchantKey}/tokens/cards", $payload);
    }

    /**
     * Get a card token.
     *
     * @throws ApiException
     */
    public function getCardToken(string $token): array
    {
        return $this->api->get("/merchants/{$this->getMerchantKey()}/tokens/cards/{$token}");
    }

    /**
     * Get all card tokens for the merchant.
     *
     * @throws ApiException
     */
    public function getCardTokens(array $filters = []): array
    {
        return $this->api->get("/merchants/{$this->getMerchantKey()}/tokens/cards", $filters);
    }

    /**
     * Update a card token (partial update).
     *
     * @throws ApiException
     */
    public function updateCardToken(string $token, array $updates): array
    {
        $payload = array_merge(['Token' => $token], $updates);

        return $this->api->patch("/merchants/{$this->getMerchantKey()}/tokens/cards/{$token}", $payload);
    }

    /**
     * Replace a card token (full replacement).
     *
     * @throws ApiException
     * @throws \InvalidArgumentException
     */
    public function replaceCardToken(string $token, array $cardDetails): array
    {
        CardValidator::validate($cardDetails);

        // Cache merchant key locally to avoid duplicate API calls
        $merchantKey = $this->getMerchantKey();

        $payload = [
            'MerchantKey' => $merchantKey,
            'Token' => $token,
            'CardNumber' => $cardDetails['number'],
            'ExpirationDate' => sprintf(
                '%02d%02d',
                $cardDetails['exp_month'],
                substr((string) $cardDetails['exp_year'], -2)
            ),
        ];

        if (isset($cardDetails['name'])) {
            $payload['NameOnCard'] = $cardDetails['name'];
        }

        if (isset($cardDetails['street'])) {
            $payload['StreetAddress'] = $cardDetails['street'];
        }

        if (isset($cardDetails['postal_code'])) {
            $payload['PostalCode'] = $cardDetails['postal_code'];
        }

        return $this->api->put("/merchants/{$merchantKey}/tokens/cards/{$token}", $payload);
    }

    /**
     * Delete one or more card tokens.
     *
     * @param  string|array  $tokens
     *
     * @throws ApiException
     */
    public function deleteCardTokens($tokens): void
    {
        $tokenString = is_array($tokens) ? implode(',', $tokens) : $tokens;
        $this->api->delete("/merchants/{$this->getMerchantKey()}/tokens/cards/{$tokenString}");
    }

    // ==================== General Token Methods ====================

    /**
     * Get all card tokens for a customer.
     *
     * @throws ApiException
     */
    public function getCustomerTokens(int $customerKey): array
    {
        return $this->api->get("/merchants/{$this->getMerchantKey()}/customers/{$customerKey}/tokens");
    }

    /**
     * Create a card token from a PnRef (transaction reference).
     *
     * @return array Returns CardToken
     *
     * @throws ApiException
     */
    public function createTokenFromPnRef(int $pnRef, ?int $customerKey = null, string $tokenFormat = 'Uid'): array
    {
        $payload = [
            'MerchantKey' => (int) $this->getMerchantKey(),
            'PnRef' => $pnRef,
            'TokenFormat' => $tokenFormat,
        ];

        if ($customerKey) {
            $payload['CustomerKey'] = $customerKey;
        }

        return $this->api->post("/merchants/{$this->getMerchantKey()}/tokens", $payload);
    }

    // ==================== Legacy Methods (for backward compatibility) ====================

    /**
     * Create a payment token from card details (legacy method).
     *
     * @deprecated Use createCardToken() instead
     *
     * @throws ApiException
     */
    public function createToken(array $cardDetails): array
    {
        return $this->createCardToken($cardDetails);
    }

    /**
     * Get token details (legacy method).
     *
     * @deprecated Use getCardToken() instead
     *
     * @throws ApiException
     */
    public function getToken(string $token): array
    {
        return $this->getCardToken($token);
    }

    /**
     * Delete a token (legacy method).
     *
     * @deprecated Use deleteCardTokens() instead
     *
     * @throws ApiException
     */
    public function deleteToken(string $token): void
    {
        $this->deleteCardTokens($token);
    }
}
