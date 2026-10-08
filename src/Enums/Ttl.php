<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Enums;

/**
 * The TTLs Openprovider accepts for a DNS record, in seconds. Openprovider saves
 * any other value as a day, so the package only lets you pick one of these.
 */
enum Ttl: int
{
    case FIFTEEN_MINUTES = 900;
    case HOUR = 3600;
    case THREE_HOURS = 10800;
    case SIX_HOURS = 21600;
    case TWELVE_HOURS = 43200;
    case DAY = 86400;
}
