<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Fluent;

/**
 * Reads Openprovider's dates (`2026-05-01 12:00:00`, no offset) as immutable Carbon
 * instances. Openprovider doesn't document their timezone, so they are read in
 * `openprovider.timezone`, or the app's timezone when that isn't set.
 */
trait ReadsDates
{
    /**
     * Openprovider's own format, used to write a date back.
     */
    protected const DATE_FORMAT = 'Y-m-d H:i:s';

    /**
     * @param  Fluent<array-key, mixed>  $data
     */
    protected static function date(Fluent $data, string $key): ?CarbonInterface
    {
        $timezone = Config::get('openprovider.timezone');

        return $data->date($key, tz: is_string($timezone) && $timezone !== '' ? $timezone : null)?->toImmutable();
    }
}
