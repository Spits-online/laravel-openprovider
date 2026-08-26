<?php

namespace Spits\LaravelOpenproviderApi\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Maatwebsite\Excel\ExcelServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spits\LaravelOpenproviderApi\LaravelOpenproviderApiServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Spits\\LaravelOpenproviderApi\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            ExcelServiceProvider::class,
            LaravelOpenproviderApiServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        /*
         foreach (\Illuminate\Support\Facades\File::allFiles(__DIR__ . '/database/migrations') as $migration) {
            (include $migration->getRealPath())->up();
         }
         */
    }
}
