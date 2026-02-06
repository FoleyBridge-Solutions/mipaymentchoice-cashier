<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable/Disable Package
    |--------------------------------------------------------------------------
    |
    | Set to false to disable the package. When disabled, API credentials
    | won't be validated at boot time, allowing the app to run without
    | payment gateway configuration.
    |
    */

    'enabled' => env('MIPAYMENTCHOICE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | MiPaymentChoice API Credentials
    |--------------------------------------------------------------------------
    |
    | Your MiPaymentChoice API credentials. These can be found in your
    | MiPaymentChoice merchant dashboard.
    |
    */

    'username' => env('MIPAYMENTCHOICE_USERNAME'),
    'password' => env('MIPAYMENTCHOICE_PASSWORD'),
    'merchant_key' => env('MIPAYMENTCHOICE_MERCHANT_KEY'),
    'quickpayments_key' => env('MIPAYMENTCHOICE_QUICKPAYMENTS_KEY'),

    /*
    |--------------------------------------------------------------------------
    | MiPaymentChoice API URL
    |--------------------------------------------------------------------------
    |
    | The base URL for the MiPaymentChoice API. Use the test URL for development
    | and the production URL for live transactions.
    |
    */

    'base_url' => env('MIPAYMENTCHOICE_BASE_URL', 'https://gateway.mipaymentchoice.com'),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | The default currency for all transactions. This should be a valid
    | ISO 4217 currency code.
    |
    */

    'currency' => env('CASHIER_CURRENCY', 'usd'),

    /*
    |--------------------------------------------------------------------------
    | Cashier Model
    |--------------------------------------------------------------------------
    |
    | This is the model in your application that includes the Billable trait
    | provided by Cashier. It will be used for all subscription operations.
    |
    */

    'model' => env('CASHIER_MODEL', 'App\\Models\\User'),

    /*
    |--------------------------------------------------------------------------
    | Customer Columns
    |--------------------------------------------------------------------------
    |
    | Define which columns on your billable entity are used for storing
    | MiPaymentChoice customer identifiers and relationships.
    |
    */

    'customer_columns' => [
        'customer_id' => 'mpc_customer_id',
        'foreign_key' => 'user_id',      // Foreign key on subscriptions/payment_methods tables
        'name' => 'name',                 // Column containing customer name
        'email' => 'email',               // Column containing customer email
    ],

    /*
    |--------------------------------------------------------------------------
    | Subscription Settings
    |--------------------------------------------------------------------------
    |
    | Configure subscription behavior including grace period defaults.
    |
    */

    'subscriptions' => [
        'grace_period_days' => env('MPC_GRACE_PERIOD_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for API calls to prevent quota exhaustion.
    |
    */

    'rate_limit' => [
        'enabled' => env('MPC_RATE_LIMIT_ENABLED', true),
        'max_requests_per_hour' => env('MPC_RATE_LIMIT_PER_HOUR', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Settings
    |--------------------------------------------------------------------------
    |
    | Configure retry behavior for transient API failures.
    |
    */

    'retry' => [
        'enabled' => env('MPC_RETRY_ENABLED', true),
        'max_attempts' => env('MPC_RETRY_MAX_ATTEMPTS', 3),
        'delay_ms' => env('MPC_RETRY_DELAY_MS', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Encryption
    |--------------------------------------------------------------------------
    |
    | Enable encryption for payment tokens stored in the database.
    | Requires Laravel's encryption key to be set.
    |
    */

    'encrypt_tokens' => env('MPC_ENCRYPT_TOKENS', true),

];
