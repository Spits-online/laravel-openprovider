<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Exceptions;

use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Openprovider answered with an error. `$status`, `$errorCode` and `$body` hold what it returned.
 */
final class RequestFailed extends OpenproviderException
{
    /**
     * @param  array<array-key, mixed>  $body
     */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly ?int $errorCode = null,
        public readonly array $body = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    public static function fromResponse(Response $response, string $action): self
    {
        $body = $response->fluent();
        $description = $body->string('desc')->value() ?: $response->reason();

        return new self(
            message: "Openprovider could not {$action} (HTTP {$response->status()}): {$description}",
            status: $response->status(),
            errorCode: $body->filled('code') ? $body->integer('code') : null,
            body: $body->toArray(),
            previous: $response->toException(),
        );
    }

    /**
     * Openprovider answered with a success status, but without what the request needs.
     *
     * @param  array<array-key, mixed>  $body
     */
    public static function unexpected(string $action, string $missing, array $body = []): self
    {
        return new self("Openprovider could not {$action}: its answer has no {$missing}.", 200, body: $body);
    }
}
