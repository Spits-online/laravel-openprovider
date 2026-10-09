<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Exceptions;

final class MissingConfiguration extends OpenproviderException
{
    public static function key(string $key, string $env): self
    {
        return new self("The `openprovider.{$key}` config value is not set. Add `{$env}` to your .env file.");
    }
}
