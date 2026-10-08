<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Exceptions;

final class MissingDependency extends OpenproviderException
{
    public static function package(string $package, string $feature): self
    {
        return new self("To {$feature}, install `{$package}`: composer require {$package}");
    }
}
