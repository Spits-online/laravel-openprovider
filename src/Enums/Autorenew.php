<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Enums;

/**
 * A domain's autorenew setting, as Openprovider names it.
 */
enum Autorenew: string
{
    case ON = 'on';
    case OFF = 'off';
    case DEFAULT = 'default';
}
