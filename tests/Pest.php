<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SpitsOnline\Openprovider\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

// A test must never reach the real Openprovider API.
beforeEach(fn () => Http::preventStrayRequests());

const OPENPROVIDER = 'https://api.openprovider.eu/v1beta';

/**
 * A response body as Openprovider sends it, from tests/Fixtures.
 *
 * @return array<string, mixed>
 */
function openproviderFixture(string $name): array
{
    return json_decode((string) file_get_contents(__DIR__."/Fixtures/{$name}.json"), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * Fake the login, plus the given endpoints (relative to the base URL).
 *
 * @param  array<string, mixed>  $responses
 */
function fakeOpenprovider(array $responses = []): void
{
    Http::fake([
        OPENPROVIDER.'/auth/login' => Http::response(openproviderFixture('login')),
        ...collect($responses)->mapWithKeys(fn (mixed $response, string $path) => [OPENPROVIDER."/{$path}" => $response])->all(),
    ]);
}
