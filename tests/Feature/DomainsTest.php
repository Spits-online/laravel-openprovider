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

it('picks a domain without sending a request', function () {
    fakeOpenprovider();

    Openprovider::domain(1222095);

    Http::assertNothingSent();
});

it('gets a domain by its id', function () {
    fakeOpenprovider(['domains/*' => Http::response(openproviderFixture('domain'))]);

    $domain = Openprovider::domain(1222095)->get();

    expect($domain)
        ->id->toBe(1222095)
        ->status->toBe('ACT')
        ->autorenew->toBe(Autorenew::OFF)
        ->isLocked->toBeTrue()
        ->isPrivateWhoisEnabled->toBeFalse()
        ->ownerHandle->toBe('CV904717-NL')
        ->adminHandle->toBe('CV904717-NL')
        ->techHandle->toBe('CV904717-NL')
        ->billingHandle->toBe('CV904717-NL')
        ->and($domain->expirationDate->format('Y-m-d H:i:s'))->toBe('2022-09-27 07:03:04')
        ->and($domain->renewalDate->format('Y-m-d H:i:s'))->toBe('2022-09-27 07:03:04')
        ->and($domain)
        ->raw->toHaveKey('ns_group', 'testin')
        ->and((string) $domain->name)->toBe('greatdomain1.info')
        ->and($domain->nameServers)->toEqual([
            new Nameserver('ns1.testin.nl'),
            new Nameserver('ns2.testin.eu', '64.190.62.111'),
        ]);

    Http::assertSent(fn (Request $request) => $request->method() === 'GET' && $request->url() === DOMAINS.'/1222095');
});

it('lists every domain lazily, a page at a time', function () {
    fakeOpenprovider(['domains*' => Http::response(openproviderFixture('domains'))]);

    $domains = Openprovider::domains()->get(pattern: 'greatdomain*', status: 'ACT');
    Http::assertNothingSent();

    expect($domains->all())->toHaveCount(2)
        ->and((string) $domains->last()->name)->toBe('greatdomain.info');

    Http::assertSent(fn (Request $request) => $request->url() === DOMAINS.'?limit=100&offset=0&domain_name_pattern=greatdomain%2A&status=ACT');
});

it('finds a domain by its full name, in one request', function () {
    fakeOpenprovider(['domains*' => Http::response(openproviderFixture('domains'))]);

    expect(Openprovider::domains()->find('greatdomain1.info')?->id)->toBe(1222095);
    Http::assertSentCount(2);

    Http::assertSent(fn (Request $request) => $request->url() === DOMAINS.'?full_name=greatdomain1.info&limit=1');
});

it('returns null for a name that is not in the account', function () {
    fakeOpenprovider(['domains*' => Http::response(['code' => 0, 'data' => ['results' => [], 'total' => 0]])]);

    expect(Openprovider::domains()->find('unknown.com'))->toBeNull();
});

it('rejects a name without an extension', function () {
    expect(fn () => Openprovider::domains()->find('localhost'))
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

    $domain = Openprovider::domains()->register(
        'greatdomain.info',
        owner: 'CV904717-NL',
        admin: 'CV904717-NL',
        nameServers: ['ns1.op.eu', Nameserver::create('ns2.op.nl', ip: '192.0.2.2')],
        autorenew: Autorenew::DEFAULT,
        attributes: ['promo_code' => 'SPRING'],
    );

    expect($domain)->id->toBe(10592139)->status->toBe('ACT')->and($domain->expirationDate->format('Y-m-d H:i:s'))->toBe('2020-04-29 17:15:19')
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

    $domain = Openprovider::domains()->transfer('example.com', authCode: 'gX38tslFG2#%F%%1', owner: 'CV904717-NL', nsGroup: 'testin');

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

    Openprovider::domain(123456)->update(autorenew: Autorenew::ON, isLocked: false, owner: 'XX123456-XX');

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && $request->url() === DOMAINS.'/123456'
        && $request->data() === ['autorenew' => 'on', 'is_locked' => false, 'owner_handle' => 'XX123456-XX']);
});

it('locks and unlocks a domain', function () {
    fakeOpenprovider(['domains/*' => Http::response(['code' => 0, 'data' => ['id' => 123456, 'status' => 'ACT']])]);

    Openprovider::domain(123456)->lock();
    Openprovider::domain(123456)->unlock();

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && $request->data() === ['is_locked' => true]);
    Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && $request->data() === ['is_locked' => false]);
});

it('renews, restores and deletes a domain', function () {
    fakeOpenprovider(['domains/*' => Http::response(openproviderFixture('domain-status'))]);

    Openprovider::domain(123456)->renew(period: 2);
    Openprovider::domain(123456)->restore();
    Openprovider::domain(123456)->delete();

    Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request->url() === DOMAINS.'/123456/renew' && $request->data() === ['period' => 2]);
    Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request->url() === DOMAINS.'/123456/restore' && $request->body() === '');
    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE' && $request->url() === DOMAINS.'/123456');
});

it('gets and resets the auth code', function () {
    fakeOpenprovider(['domains/*' => Http::response(openproviderFixture('authcode'))]);

    expect(Openprovider::domain(123456)->authCode())->toBe('12345678')
        ->and(Openprovider::domain(123456)->resetAuthCode())->toBe('12345678');

    Http::assertSent(fn (Request $request) => $request->method() === 'GET' && $request->url() === DOMAINS.'/123456/authcode');
    Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request->url() === DOMAINS.'/123456/authcode/reset');
});

it('throws when the answer has no auth code', function () {
    fakeOpenprovider(['domains/*' => Http::response(['code' => 0, 'data' => ['success' => false]])]);

    expect(fn () => Openprovider::domain(123456)->authCode())
        ->toThrow(RequestFailed::class, 'Openprovider could not get the auth code of domain 123456: its answer has no auth code.');
});

it('lets a domain you fetched act on itself, one request each', function () {
    fakeOpenprovider([
        'domains?*' => Http::response(openproviderFixture('domains')),
        'domains/*' => Http::response(openproviderFixture('domain-status')),
    ]);

    $domain = Openprovider::domains()->find('greatdomain1.info');
    $domain->renew();
    $domain->lock();

    Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request->url() === DOMAINS.'/1222095/renew');
    Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && $request->url() === DOMAINS.'/1222095' && $request->data() === ['is_locked' => true]);
    Http::assertSentCount(4);
});

it('leaves the client out when a domain is serialized, so a queued job never stores the password', function () {
    fakeOpenprovider(['domains/*' => Http::response(openproviderFixture('domain'))]);
    config()->set('openprovider.password', 'secret-password');
    app()->forgetInstance(SpitsOnline\Openprovider\Openprovider::class);

    $serialized = serialize(Openprovider::domain(1222095)->get());

    expect($serialized)->not->toContain('secret-password')
        ->and(unserialize($serialized)->id)->toBe(1222095);
});
