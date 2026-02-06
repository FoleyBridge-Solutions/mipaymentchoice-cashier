<?php

declare(strict_types=1);

namespace FoleyBridgeSolutions\MiPaymentChoiceCashier\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use FoleyBridgeSolutions\MiPaymentChoiceCashier\CashierServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            CashierServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('mipaymentchoice.username', 'test_user');
        $app['config']->set('mipaymentchoice.password', 'test_pass');
        $app['config']->set('mipaymentchoice.base_url', 'https://api.test.example.com');
    }
}
