<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Enums;

/**
 * A master zone holds its own records. A slave zone copies them from a master server.
 */
enum ZoneType: string
{
    case MASTER = 'master';
    case SLAVE = 'slave';
}
