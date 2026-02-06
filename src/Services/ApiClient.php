<?php

declare(strict_types=1);

namespace FoleyBridgeSolutions\MiPaymentChoiceCashier\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use FoleyBridgeSolutions\MiPaymentChoiceCashier\Exceptions\ApiException;

/**
 * ApiClient
 *
 * HTTP client for communicating with the MiPaymentChoice API.
 * Handles authentication, token caching, retry logic, and request/response processing.
 */
class ApiClient
{
    /**
     * The HTTP client instance.
     *
     * @var Client
     */
    protected Client $client;

    /**
     * The API username.
     *
     * @var string
     */
    protected string $username;

    /**
     * The API password.
     *
     * @var string
     */
    protected string $password;

    /**
     * The base URL for the API.
     *
     * @var string
     */
    protected string $baseUrl;

    /**
     * Cache TTL for auth data (55 minutes - 5 minute buffer before 1 hour expiry).
     */
    protected const AUTH_CACHE_TTL = 3300;

    /**
     * Create a new API client instance.
     *
     * @param  string  $username  API username
     * @param  string  $password  API password
     * @param  string  $baseUrl   Base URL for API endpoints
     * @throws ApiException  If required credentials are missing
     */
    public function __construct(string $username, string $password, string $baseUrl)
    {
        // Defer validation if package is disabled
        if (config('mipaymentchoice.enabled', true)) {
            if (empty($username) || empty($password)) {
                throw new ApiException('MiPaymentChoice API credentials are required. Set MIPAYMENTCHOICE_USERNAME and MIPAYMENTCHOICE_PASSWORD in your .env file.');
            }

            if (empty($baseUrl)) {
                throw new ApiException('MiPaymentChoice API base URL is required. Set MIPAYMENTCHOICE_BASE_URL in your .env file.');
            }
        }

        $this->username = $username;
        $this->password = $password;
        $this->baseUrl = rtrim($baseUrl ?: 'https://gateway.mipaymentchoice.com', '/');
        
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
        ]);
    }

    /**
     * Get cached authentication data (token and merchant key).
     *
     * Caches the full auth response for 55 minutes (5-minute buffer before 1-hour expiry)
     * to avoid race conditions and duplicate authentication requests.
     *
     * @return array  Authentication data containing BearerToken and parsed merchant key
     * @throws ApiException  If authentication fails
     */
    protected function getAuthData(): array
    {
        $cacheKey = 'mipaymentchoice_auth_' . md5($this->username);

        return Cache::remember($cacheKey, self::AUTH_CACHE_TTL, function () {
            $authData = $this->authenticate();
            
            // Pre-parse and cache the merchant key from JWT
            $token = $authData['BearerToken'];
            $parts = explode('.', $token);
            
            if (count($parts) !== 3) {
                throw new ApiException('Invalid JWT token format');
            }
            
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
            
            if (!isset($payload['vk'])) {
                throw new ApiException('Merchant key not found in token');
            }
            
            $authData['MerchantKey'] = (int) $payload['vk'];
            
            return $authData;
        });
    }

    /**
     * Get or refresh the bearer token.
     *
     * @return string  The bearer token
     * @throws ApiException  If authentication fails
     */
    protected function getBearerToken(): string
    {
        return $this->getAuthData()['BearerToken'];
    }

    /**
     * Authenticate and return the full response including BearerToken.
     *
     * @return array  Authentication response containing BearerToken
     * @throws ApiException  If authentication fails
     */
    protected function authenticate(): array
    {
        try {
            $response = $this->client->request('POST', '/api/authenticate', [
                'json' => [
                    'UserName' => $this->username,
                    'Password' => $this->password,
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['BearerToken'])) {
                return $data;
            }

            throw new ApiException('Failed to retrieve bearer token');
        } catch (GuzzleException $e) {
            throw new ApiException('Authentication failed: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Get the merchant key from the cached auth data.
     *
     * @return int  The merchant key
     * @throws ApiException  If token is invalid or merchant key not found
     */
    public function getMerchantKey(): int
    {
        return $this->getAuthData()['MerchantKey'];
    }

    /**
     * Clear the cached authentication data.
     *
     * Useful when credentials change or token needs to be refreshed.
     *
     * @return void
     */
    public function clearAuthCache(): void
    {
        Cache::forget('mipaymentchoice_auth_' . md5($this->username));
    }

    /**
     * Make a POST request to the API.
     *
     * @param  string  $endpoint  API endpoint path
     * @param  array   $data      Request body data
     * @return array   Response data
     * @throws ApiException  If request fails
     */
    public function post(string $endpoint, array $data = []): array
    {
        return $this->requestWithRetry('POST', $endpoint, $data);
    }

    /**
     * Make a GET request to the API.
     *
     * @param  string  $endpoint  API endpoint path
     * @param  array   $query     Query string parameters
     * @return array   Response data
     * @throws ApiException  If request fails
     */
    public function get(string $endpoint, array $query = []): array
    {
        return $this->requestWithRetry('GET', $endpoint, [], $query);
    }

    /**
     * Make a PUT request to the API.
     *
     * @param  string  $endpoint  API endpoint path
     * @param  array   $data      Request body data
     * @return array   Response data
     * @throws ApiException  If request fails
     */
    public function put(string $endpoint, array $data = []): array
    {
        return $this->requestWithRetry('PUT', $endpoint, $data);
    }

    /**
     * Make a PATCH request to the API.
     *
     * @param  string  $endpoint  API endpoint path
     * @param  array   $data      Request body data
     * @return array   Response data
     * @throws ApiException  If request fails
     */
    public function patch(string $endpoint, array $data = []): array
    {
        return $this->requestWithRetry('PATCH', $endpoint, $data);
    }

    /**
     * Make a DELETE request to the API.
     *
     * @param  string  $endpoint  API endpoint path
     * @return array   Response data
     * @throws ApiException  If request fails
     */
    public function delete(string $endpoint): array
    {
        return $this->requestWithRetry('DELETE', $endpoint);
    }

    /**
     * Make a request with retry logic for transient failures.
     *
     * @param  string  $method    HTTP method
     * @param  string  $endpoint  API endpoint path
     * @param  array   $data      Request body data
     * @param  array   $query     Query string parameters
     * @return array   Response data
     * @throws ApiException  If all retries fail
     */
    protected function requestWithRetry(string $method, string $endpoint, array $data = [], array $query = []): array
    {
        $retryEnabled = config('mipaymentchoice.retry.enabled', true);
        $maxAttempts = $retryEnabled ? config('mipaymentchoice.retry.max_attempts', 3) : 1;
        $delayMs = config('mipaymentchoice.retry.delay_ms', 100);
        
        $attempts = 0;
        $lastException = null;

        while ($attempts < $maxAttempts) {
            try {
                $this->checkRateLimit();
                return $this->request($method, $endpoint, $data, $query);
            } catch (ApiException $e) {
                $lastException = $e;
                $attempts++;

                if (!$this->isRetryable($e) || $attempts >= $maxAttempts) {
                    throw $e;
                }

                // Exponential backoff: 100ms, 200ms, 400ms...
                $sleepMs = $delayMs * pow(2, $attempts - 1);
                usleep($sleepMs * 1000);

                Log::warning('MiPaymentChoice API retry', [
                    'attempt' => $attempts,
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        throw $lastException ?? new ApiException('Request failed after retries');
    }

    /**
     * Check if the exception is retryable.
     *
     * @param  ApiException  $e
     * @return bool
     */
    protected function isRetryable(ApiException $e): bool
    {
        $code = $e->getCode();
        
        // Retry on server errors, rate limits, and connection issues
        return $code >= 500 || $code === 429 || $code === 0;
    }

    /**
     * Check rate limit before making a request.
     *
     * Uses atomic Cache::increment() to prevent race conditions.
     *
     * @return void
     * @throws ApiException  If rate limit exceeded
     */
    protected function checkRateLimit(): void
    {
        if (!config('mipaymentchoice.rate_limit.enabled', true)) {
            return;
        }

        $maxRequests = config('mipaymentchoice.rate_limit.max_requests_per_hour', 1000);
        $cacheKey = 'mpc_rate_limit_' . date('YmdH');

        // Atomic increment - prevents race condition
        $currentCount = Cache::increment($cacheKey);

        // Set expiry on first request of the hour
        if ($currentCount === 1) {
            Cache::put($cacheKey, 1, 3600);
        }

        if ($currentCount > $maxRequests) {
            throw new ApiException('API rate limit exceeded. Maximum ' . $maxRequests . ' requests per hour.', [], 429);
        }
    }

    /**
     * Make a request to the API.
     *
     * Handles authentication, request building, and error processing.
     *
     * @param  string  $method    HTTP method (GET, POST, PUT, PATCH, DELETE)
     * @param  string  $endpoint  API endpoint path
     * @param  array   $data      Request body data (for POST, PUT, PATCH)
     * @param  array   $query     Query string parameters (for GET)
     * @return array   Response data
     * @throws ApiException  If request fails
     */
    protected function request(string $method, string $endpoint, array $data = [], array $query = []): array
    {
        try {
            $token = $this->getBearerToken();

            $options = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
            ];

            if (!empty($data)) {
                $options['json'] = $data;
            }

            if (!empty($query)) {
                $options['query'] = $query;
            }

            $response = $this->client->request($method, $endpoint, $options);
            $body = $response->getBody()->getContents();

            return json_decode($body, true) ?? [];
        } catch (GuzzleException $e) {
            $message = $e->getMessage();
            $response = [];
            $statusCode = 0;

            // Extract error details from response if available
            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $body = $e->getResponse()->getBody()->getContents();
                $response = json_decode($body, true) ?? [];
                
                // Use API error message if available
                if (isset($response['ResponseStatus']['Message'])) {
                    $message = $response['ResponseStatus']['Message'];
                } elseif (isset($response['Message'])) {
                    $message = $response['Message'];
                }
            }

            throw new ApiException($message, is_array($response) ? $response : [], $statusCode, $e);
        }
    }
}
