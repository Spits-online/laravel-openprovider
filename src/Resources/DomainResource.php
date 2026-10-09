<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Resources;

use Illuminate\Support\Arr;
use Illuminate\Support\Fluent;
use SpitsOnline\Openprovider\Data\Domain;
use SpitsOnline\Openprovider\Data\Nameserver;
use SpitsOnline\Openprovider\Enums\Autorenew;
use SpitsOnline\Openprovider\Exceptions\RequestFailed;
use SpitsOnline\Openprovider\Openprovider;

/**
 * One domain: `Openprovider::domain($id)`. Picking a domain sends no request; every
 * method after it sends exactly one. A `Domain` you fetched has the same methods.
 */
class DomainResource
{
    public function __construct(
        protected Openprovider $openprovider,
        protected int $id,
    ) {}

    public function get(): Domain
    {
        return Domain::fromArray($this->openprovider->request('get', $this->path(), "find domain {$this->id}")->toArray(), $this->openprovider);
    }

    /**
     * Change the domain. Only the arguments you pass are changed. Contact handles are
     * Openprovider customer handles, e.g. `CV904717-NL`.
     *
     * @param  ?list<Nameserver|string>  $nameServers
     * @param  array<string, mixed>  $attributes  any other field Openprovider documents for the request
     */
    public function update(
        ?array $nameServers = null,
        ?string $nsGroup = null,
        ?Autorenew $autorenew = null,
        ?bool $isLocked = null,
        ?bool $isPrivateWhoisEnabled = null,
        ?string $owner = null,
        ?string $admin = null,
        ?string $tech = null,
        ?string $billing = null,
        ?string $comments = null,
        array $attributes = [],
    ): void {
        $this->openprovider->request('put', $this->path(), "update domain {$this->id}", body: Arr::whereNotNull([
            'name_servers' => $nameServers === null ? null : Domains::nameServers($nameServers),
            'ns_group' => $nsGroup,
            'autorenew' => $autorenew?->value,
            'is_locked' => $isLocked,
            'is_private_whois_enabled' => $isPrivateWhoisEnabled,
            'owner_handle' => $owner,
            'admin_handle' => $admin,
            'tech_handle' => $tech,
            'billing_handle' => $billing,
            'comments' => $comments,
        ]) + $attributes);
    }

    /**
     * Lock the domain, so it can't be transferred away.
     */
    public function lock(): void
    {
        $this->update(isLocked: true);
    }

    public function unlock(): void
    {
        $this->update(isLocked: false);
    }

    /**
     * @param  int  $period  the number of renewals; a yearly domain is renewed a year each
     */
    public function renew(int $period = 1): void
    {
        $this->openprovider->request('post', $this->path().'/renew', "renew domain {$this->id}", body: ['period' => $period]);
    }

    /**
     * Restore the domain after it was deleted.
     */
    public function restore(): void
    {
        $this->openprovider->request('post', $this->path().'/restore', "restore domain {$this->id}");
    }

    public function delete(): void
    {
        $this->openprovider->request('delete', $this->path(), "delete domain {$this->id}");
    }

    /**
     * The domain's transfer auth code.
     */
    public function authCode(): string
    {
        return $this->authCodeFrom($this->openprovider->request('get', $this->path().'/authcode', "get the auth code of domain {$this->id}"), 'get');
    }

    /**
     * Replace the domain's auth code with a new one, and return it.
     */
    public function resetAuthCode(): string
    {
        return $this->authCodeFrom($this->openprovider->request('post', $this->path().'/authcode/reset', "reset the auth code of domain {$this->id}"), 'reset');
    }

    /**
     * @param  Fluent<array-key, mixed>  $data
     */
    protected function authCodeFrom(Fluent $data, string $verb): string
    {
        $authCode = $data->string('auth_code')->value();

        return $authCode !== '' ? $authCode : throw RequestFailed::unexpected("{$verb} the auth code of domain {$this->id}", 'auth code', $data->toArray());
    }

    protected function path(): string
    {
        return "domains/{$this->id}";
    }
}
