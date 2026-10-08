<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Exceptions;

final class InvalidDomainName extends OpenproviderException
{
    public static function from(string $domain): self
    {
        return new self("`{$domain}` is not a domain name. Pass one with an extension, e.g. `example.com`.");
    }
}
