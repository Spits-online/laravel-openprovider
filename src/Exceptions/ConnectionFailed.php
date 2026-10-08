<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Exceptions;

use Illuminate\Http\Client\ConnectionException;

final class ConnectionFailed extends OpenproviderException
{
    public static function from(ConnectionException $exception): self
    {
        return new self("Could not connect to the Openprovider API: {$exception->getMessage()}", previous: $exception);
    }
}
