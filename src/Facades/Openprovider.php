<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Facades;

use Illuminate\Support\Facades\Facade;
use SpitsOnline\Openprovider\Openprovider as OpenproviderClient;
use SpitsOnline\Openprovider\Testing\OpenproviderFake;

/**
 * @method static \SpitsOnline\Openprovider\Resources\Zones zones()
 * @method static \SpitsOnline\Openprovider\Resources\Domains domains()
 * @method static void assertZoneCreated(string $name)
 * @method static void assertZoneDeleted(string $name)
 * @method static void assertRecordAdded(string $zone, ?callable $callback = null)
 * @method static void assertRecordUpdated(string $zone, ?callable $callback = null)
 * @method static void assertRecordRemoved(string $zone, ?callable $callback = null)
 * @method static void assertDomainRegistered(string $name)
 * @method static void assertDomainTransferred(string $name)
 * @method static void assertDomainUpdated(int $id, ?callable $callback = null)
 * @method static void assertDomainRenewed(int $id)
 * @method static void assertDomainRestored(int $id)
 * @method static void assertDomainDeleted(int $id)
 * @method static void assertNothingChanged()
 *
 * @see OpenproviderClient
 * @see OpenproviderFake
 */
final class Openprovider extends Facade
{
    public static function fake(): OpenproviderFake
    {
        self::swap($fake = new OpenproviderFake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return OpenproviderClient::class;
    }
}
