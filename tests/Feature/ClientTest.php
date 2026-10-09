<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use SpitsOnline\Openprovider\Exceptions\ConnectionFailed;
use SpitsOnline\Openprovider\Exceptions\MissingConfiguration;
use SpitsOnline\Openprovider\Exceptions\OpenproviderException;
use SpitsOnline\Openprovider\Exceptions\RequestFailed;
use SpitsOnline\Openprovider\Facades\Openprovider;
use SpitsOnline\Openprovider\Openprovider as OpenproviderClient;

it('logs in with the configured account and sends the token', function () {
    fakeOpenprovider(['dns/zones/*' => Http::response(openproviderFixture('zone'))]);

    Openprovider::zone('demo-domain.nl')->get();

    Http::assertSent(fn (Request $request) => $request->url() === OPENPROVIDER.'/auth/login'
        && $request->method() === 'POST'
        && $request->data() === ['username' => 'spits', 'password' => 'secret', 'ip' => '203.0.113.10']);

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), OPENPROVIDER.'/dns/zones/')
        && $request->hasHeader('Authorization', 'Bearer 6f6d86377bc1f2e3a4b5c6d7feb75cea76d8e8b')
        && $request->hasHeader('Accept', 'application/json'));
});

it('logs in once and reuses the token from the cache', function () {
    fakeOpenprovider(['dns/zones/*' => Http::response(openproviderFixture('zone'))]);

    Openprovider::zone('demo-domain.nl')->get();
    Openprovider::zone('demo-domain.nl')->get();

    Http::assertSentCount(3);
});

it('caches the token for just under the 48 hours it is valid', function () {
    fakeOpenprovider(['dns/zones/*' => Http::response(openproviderFixture('zone'))]);

    Openprovider::zone('demo-domain.nl')->get();
    $this->travel(47 * 60 - 1)->minutes();
    Openprovider::zone('demo-domain.nl')->get();
    $this->travel(2)->minutes();
    Openprovider::zone('demo-domain.nl')->get();

    Http::assertSentCount(5);
});

it('keeps a separate token per account', function () {
    fakeOpenprovider(['dns/zones/*' => Http::response(openproviderFixture('zone'))]);

    Openprovider::zone('demo-domain.nl')->get();
    OpenproviderClient::fromConfig(['username' => 'other', 'password' => 'secret'])->zone('demo-domain.nl')->get();

    Http::assertSent(fn (Request $request) => ($request->data()['username'] ?? null) === 'other');
    Http::assertSentCount(4);
});

it('uses the configured base url, such as the sandbox', function () {
    $sandbox = 'http://api.sandbox.openprovider.nl:8480/v1beta';

    Http::fake([
        "{$sandbox}/auth/login" => Http::response(openproviderFixture('login')),
        "{$sandbox}/dns/zones/*" => Http::response(openproviderFixture('zone')),
    ]);

    OpenproviderClient::fromConfig([...config('openprovider'), 'base_url' => $sandbox])->zone('demo-domain.nl')->get();

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), "{$sandbox}/dns/zones/"));
});

it('names the env key of missing credentials', function (string $key, string $env) {
    config()->set("openprovider.{$key}");
    app()->forgetInstance(OpenproviderClient::class);
    Openprovider::clearResolvedInstances();

    expect(fn () => Openprovider::zone('demo-domain.nl')->get())
        ->toThrow(MissingConfiguration::class, "Add `{$env}` to your .env file.");

    Http::assertNothingSent();
})->with([
    'username' => ['username', 'OPENPROVIDER_USERNAME'],
    'password' => ['password', 'OPENPROVIDER_PASSWORD'],
]);

it('throws RequestFailed with what Openprovider answered', function () {
    fakeOpenprovider(['dns/zones/*' => Http::response(openproviderFixture('error'), 400)]);

    try {
        Openprovider::zone('missing.nl')->get();
        $this->fail('Expected RequestFailed.');
    } catch (RequestFailed $e) {
        expect($e)
            ->getMessage()->toBe('Openprovider could not find zone `missing.nl` (HTTP 400): Test error description')
            ->status->toBe(400)
            ->errorCode->toBe(1)
            ->body->toBe(openproviderFixture('error'))
            ->getPrevious()->not->toBeNull();
    }
});

it('throws RequestFailed when the login is refused, and caches nothing', function () {
    Http::fake([OPENPROVIDER.'/auth/login' => Http::response(openproviderFixture('error'), 401)]);

    expect(fn () => Openprovider::zone('demo-domain.nl')->get())
        ->toThrow(RequestFailed::class, 'Openprovider could not log in (HTTP 401)');

    expect(Cache::get('openprovider.token.'.hash('sha256', OPENPROVIDER.'|spits|203.0.113.10')))->toBeNull();
});

it('throws RequestFailed when the login answer has no token', function () {
    Http::fake([OPENPROVIDER.'/auth/login' => Http::response(['code' => 0, 'data' => []])]);

    expect(fn () => Openprovider::zone('demo-domain.nl')->get())
        ->toThrow(RequestFailed::class, 'Openprovider could not log in: its answer has no token.');
});

it('throws ConnectionFailed when Openprovider cannot be reached', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

    expect(fn () => Openprovider::zone('demo-domain.nl')->get())
        ->toThrow(ConnectionFailed::class, 'Could not connect to the Openprovider API: cURL error 28: timed out');
});

it('lets one catch block handle every package exception', function () {
    Http::fake(fn () => throw new ConnectionException('down'));

    expect(fn () => Openprovider::domain(1)->get())->toThrow(OpenproviderException::class);
});
