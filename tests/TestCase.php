<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Tests;

use Maatwebsite\Excel\ExcelServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use SpitsOnline\Openprovider\OpenproviderServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ExcelServiceProvider::class, OpenproviderServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('openprovider.username', 'spits');
        $app['config']->set('openprovider.password', 'secret');
        $app['config']->set('openprovider.ip', '203.0.113.10');

        // The `web` middleware group of the DNS record routes encrypts cookies.
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }
}
