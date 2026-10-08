<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Enums;

/**
 * A domain's autorenew setting, as Openprovider names it.
 */
enum Autorenew: string
{
    case On = 'on';
    case Off = 'off';
    case Default = 'default';
}
