<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Enums;

/**
 * The premium DNS providers Openprovider can host a zone with. Leave the provider
 * out for Openprovider's own DNS.
 */
enum Provider: string
{
    case SECTIGO = 'sectigo';
}
