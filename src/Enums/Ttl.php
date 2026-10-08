<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Enums;

/**
 * The TTLs Openprovider accepts for a DNS record, in seconds. Openprovider saves
 * any other value as a day, so the package only lets you pick one of these.
 */
enum Ttl: int
{
    case FifteenMinutes = 900;
    case Hour = 3600;
    case ThreeHours = 10800;
    case SixHours = 21600;
    case TwelveHours = 43200;
    case Day = 86400;
}
