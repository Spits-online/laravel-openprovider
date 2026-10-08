<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SpitsOnline\Openprovider\Data\DomainCheck;
use SpitsOnline\Openprovider\Data\Nameserver;
use SpitsOnline\Openprovider\Enums\Autorenew;
use SpitsOnline\Openprovider\Exceptions\InvalidDomainName;
use SpitsOnline\Openprovider\Exceptions\RequestFailed;
use SpitsOnline\Openprovider\Facades\Openprovider;

const DOMAINS = OPENPROVIDER.'/domains';

it('finds a domain', function () {
    fakeOpenprovider(['domains/*' => Http::response(openproviderFixture('domain'))]);

    $domain = Openprovider::domains()->find(1222095);

    expect($domain)
        ->id->toBe(1222095)
        ->status->toBe('ACT')
        ->autorenew->toBe(Autorenew::Off)
        ->isLocked->toBeTrue()
        ->isPrivateWhoisEnabled->toBeFalse()
        ->ownerHandle->toBe('CV904717-NL')
        ->adminHandle->toBe('CV904717-NL')
        ->techHandle->toBe('CV904717-NL')
        ->billingHandle->toBe('CV904717-NL')
        ->expirationDate->toBe('2022-09-27 07:03:04')
        ->renewalDate->toBe('2022-09-27 07:03:04')
        ->raw->toHaveKey('ns_group', 'testin')
        ->and((string) $domain->name)->toBe('greatdomain1.info')
        ->and($domain->nameServers)->toEqual([
            new Nameserver('ns1.testin.nl'),
            new Nameserver('ns2.testin.eu', '64.190.62.111'),
        ]);

    Http::assertSent(fn (Request $request) => $request->method() === 'GET' && $request->url() === DOMAINS.'/1222095');
});

it('lists a page of domains', function () {
    fakeOpenprovider(['domains*' => Http::response(openproviderFixture('domains'))]);

    $page = Openprovider::domains()->list(limit: 2, pattern: 'greatdomain*', status: 'ACT');

    expect($page)->total->toBe(2)->items->toHaveCount(2)
        ->and((string) $page->items[1]->name)->toBe('greatdomain.info');

    Http::assertSent(fn (Request $request) => $request->url() === DOMAINS.'?limit=2&offset=0&domain_name_pattern=greatdomain%2A&status=ACT');
});

it('walks every domain lazily', function () {
    fakeOpenprovider(['domains*' => Http::response(openproviderFixture('domains'))]);

    expect(Openprovider::domains()->all()->count())->toBe(2);

    Http::assertSent(fn (Request $request) => $request->url() === DOMAINS.'?limit=100&offset=0');
});

it('finds a domain by its full name', function () {
    fakeOpenprovider(['domains*' => Http::response(openproviderFixture('domains'))]);

    expect(Openprovider::domains()->findByName('greatdomain1.info')?->id)->toBe(1222095);

    Http::assertSent(fn (Request $request) => $request->url() === DOMAINS.'?full_name=greatdomain1.info&limit=1');
});

it('returns null for a name that is not in the account', function () {
    fakeOpenprovider(['domains*' => Http::response(['code' => 0, 'data' => ['results' => [], 'total' => 0]])]);

    expect(Openprovider::domains()->findByName('unknown.com'))->toBeNull();
});

it('rejects a name without an extension', function () {
    expect(fn () => Openprovider::domains()->findByName('localhost'))
        ->toThrow(InvalidDomainName::class, '`localhost` is not a domain name.');
});

it('checks whether domains are available', function () {
    fakeOpenprovider(['domains/check' => Http::response(openproviderFixture('domain-check'))]);

    $results = Openprovider::domains()->check(['some-amazing-example.com', 'god.tools'], withPrice: true);

    expect($results)->toHaveCount(2)->each->toBeInstanceOf(DomainCheck::class)
        ->and($results[0])->domain->toBe('some-amazing-example.com')->isPremium->toBeFalse()
        ->and($results[0]->isAvailable())->toBeTrue()
        ->and($results[1]->isPremium)->toBeTrue()
        ->and($results[1]->raw['price']['reseller'])->toBe(['currency' => 'EUR', 'price' => 219.8]);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request->data() === [
        'domains' => [
            ['name' => 'some-amazing-example', 'extension' => 'com'],
            ['name' => 'god', 'extension' => 'tools'],
        ],
        'with_price' => true,
    ]);
});

it('registers a domain', function () {
    fakeOpenprovider(['domains' => Http::response(openproviderFixture('domain-registered'))]);

    $domain = Openprovider::domains()->create(
        'greatdomain.info',
        ownerHandle: 'CV904717-NL',
        adminHandle: 'CV904717-NL',
        nameServers: ['ns1.op.eu', Nameserver::create('ns2.op.nl', ip: '192.0.2.2')],
        autorenew: Autorenew::Default,
        attributes: ['promo_code' => 'SPRING'],
    );

    expect($domain)->id->toBe(10592139)->status->toBe('ACT')->expirationDate->toBe('2020-04-29 17:15:19')
        ->and((string) $domain->name)->toBe('greatdomain.info');

    Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request->url() === DOMAINS && $request->data() === [
        'domain' => ['name' => 'greatdomain', 'extension' => 'info'],
        'owner_handle' => 'CV904717-NL',
        'admin_handle' => 'CV904717-NL',
        'period' => 1,
        'name_servers' => [['name' => 'ns1.op.eu'], ['name' => 'ns2.op.nl', 'ip' => '192.0.2.2']],
        'autorenew' => 'default',
        'promo_code' => 'SPRING',
    ]);
});

it('transfers a domain', function () {
    fakeOpenprovider(['domains/transfer' => Http::response(openproviderFixture('domain-transferred'))]);

    $domain = Openprovider::domains()->transfer('example.com', authCode: 'gX38tslFG2#%F%%1', ownerHandle: 'CV904717-NL', nsGroup: 'testin');

    expect($domain->status)->toBe('REQ')->and((string) $domain->name)->toBe('example.com');

    Http::assertSent(fn (Request $request) => $request->data() === [
        'domain' => ['name' => 'example', 'extension' => 'com'],
        'auth_code' => 'gX38tslFG2#%F%%1',
        'owner_handle' => 'CV904717-NL',
        'ns_group' => 'testin',
    ]);
});

it('updates only what is passed', function () {
    fakeOpenprovider(['domains/*' => Http::response(['code' => 0, 'data' => ['id' => 123456, 'status' => 'ACT']])]);

    Openprovider::domains()->update(123456, autorenew: Autorenew::On, isLocked: false, ownerHandle: 'XX123456-XX');

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && $request->url() === DOMAINS.'/123456'
        && $request->data() === ['autorenew' => 'on', 'is_locked' => false, 'owner_handle' => 'XX123456-XX']);
});

it('renews, restores and deletes a domain', function () {
    fakeOpenprovider(['domains/*' => Http::response(openproviderFixture('domain-status'))]);

    Openprovider::domains()->renew(123456, period: 2);
    Openprovider::domains()->restore(123456);
    Openprovider::domains()->delete(123456);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request->url() === DOMAINS.'/123456/renew' && $request->data() === ['period' => 2]);
    Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request->url() === DOMAINS.'/123456/restore' && $request->body() === '');
    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE' && $request->url() === DOMAINS.'/123456');
});

it('gets and resets the auth code', function () {
    fakeOpenprovider(['domains/*' => Http::response(openproviderFixture('authcode'))]);

    expect(Openprovider::domains()->authCode(123456))->toBe('12345678')
        ->and(Openprovider::domains()->resetAuthCode(123456))->toBe('12345678');

    Http::assertSent(fn (Request $request) => $request->method() === 'GET' && $request->url() === DOMAINS.'/123456/authcode');
    Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request->url() === DOMAINS.'/123456/authcode/reset');
});

it('throws when the answer has no auth code', function () {
    fakeOpenprovider(['domains/*' => Http::response(['code' => 0, 'data' => ['success' => false]])]);

    expect(fn () => Openprovider::domains()->authCode(123456))
        ->toThrow(RequestFailed::class, 'Openprovider could not get the auth code of domain 123456: its answer has no auth code.');
});
