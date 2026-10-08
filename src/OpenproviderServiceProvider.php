<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class OpenproviderServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('openprovider')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->mergeConfigRecursively();

        $this->app->singleton(Openprovider::class, fn () => Openprovider::fromConfig(Config::array('openprovider')));
    }

    public function packageBooted(): void
    {
        if (! Config::boolean('openprovider.routes.enabled') || $this->app->routesAreCached()) {
            return;
        }

        Route::middleware(Config::array('openprovider.routes.middleware'))
            ->prefix(Config::string('openprovider.routes.prefix'))
            ->group(__DIR__.'/../routes/openprovider.php');
    }

    /**
     * Laravel merges a package config one level deep, so an app that sets only
     * `routes.enabled` would lose `routes.middleware`. Merging the package file
     * underneath again, key by key, lets the app's `config/openprovider.php` state
     * only what differs. The config cache already holds the merged result.
     */
    private function mergeConfigRecursively(): void
    {
        if ($this->app->configurationIsCached()) {
            return;
        }

        Config::set('openprovider', self::merge(Arr::wrap(require __DIR__.'/../config/openprovider.php'), Config::array('openprovider', [])));
    }

    /**
     * Associative arrays merge recursively, lists are replaced wholesale: an app
     * that lists two middleware means those two.
     *
     * @param  array<array-key, mixed>  $base
     * @param  array<array-key, mixed>  $override
     * @return array<array-key, mixed>
     */
    private static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            $base[$key] = is_array($value)
                && is_array($base[$key] ?? null)
                && ! array_is_list($value)
                && ! array_is_list($base[$key])
                    ? self::merge($base[$key], $value)
                    : $value;
        }

        return $base;
    }
}
