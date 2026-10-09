<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Concerns;

use Illuminate\Support\Arr;
use Illuminate\Support\Fluent;
use SpitsOnline\Openprovider\Exceptions\RequestFailed;

/**
 * Zone changes answer `success: true` when Openprovider made them. Anything else
 * means the change didn't happen, so it throws instead of passing silently.
 */
trait ConfirmsChanges
{
    /**
     * Send a change and make sure Openprovider reports it as done. Null fields are left out.
     *
     * @param  'post'|'put'|'delete'  $method
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $body
     */
    protected function change(string $method, string $path, string $action, array $query = [], array $body = []): void
    {
        $data = $this->openprovider->request($method, $path, $action, Arr::whereNotNull($query), Arr::whereNotNull($body));

        $this->ensureSuccess($data, $action);
    }

    /**
     * @param  Fluent<array-key, mixed>  $data
     */
    protected function ensureSuccess(Fluent $data, string $action): void
    {
        if ($data->get('success') !== true) {
            throw RequestFailed::unexpected($action, '`success: true`', $data->toArray());
        }
    }
}
