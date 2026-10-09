<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
use SpitsOnline\Openprovider\Exceptions\ConnectionFailed;
use SpitsOnline\Openprovider\Exceptions\MissingConfiguration;
use SpitsOnline\Openprovider\Exceptions\RequestFailed;
use SpitsOnline\Openprovider\Resources\DomainResource;
use SpitsOnline\Openprovider\Resources\Domains;
use SpitsOnline\Openprovider\Resources\ZoneResource;
use SpitsOnline\Openprovider\Resources\Zones;

class Openprovider
{
    /**
     * Openprovider's tokens are valid for 48 hours. Logging in again an hour early
     * means a cached token never expires halfway through a request.
     */
    protected const int TOKEN_TTL = 47 * 60 * 60;

    public function __construct(
        protected ?string $username,
        protected ?string $password,
        protected ?string $ip,
        protected string $baseUrl,
    ) {}

    /**
     * @param  array<array-key, mixed>  $config
     */
    public static function fromConfig(array $config): self
    {
        $config = new Fluent($config);

        return new self(
            username: $config->string('username')->value() ?: null,
            password: $config->string('password')->value() ?: null,
            ip: $config->string('ip')->value() ?: null,
            baseUrl: $config->string('base_url')->value() ?: 'https://api.openprovider.eu/v1beta',
        );
    }

    /**
     * Every DNS zone in the account, and creating new ones.
     */
    public function zones(): Zones
    {
        return new Zones($this);
    }

    /**
     * One DNS zone, by name. Sends no request until you call a method on it.
     */
    public function zone(string $name): ZoneResource
    {
        return new ZoneResource($this, $name);
    }

    /**
     * Every domain in the account, and registering or transferring new ones.
     */
    public function domains(): Domains
    {
        return new Domains($this);
    }

    /**
     * One domain, by its Openprovider id. Sends no request until you call a method on
     * it. Only have the name? `domains()->find('example.com')` looks it up.
     */
    public function domain(int $id): DomainResource
    {
        return new DomainResource($this, $id);
    }

    /**
     * Send a request and return the `data` of Openprovider's answer.
     *
     * @param  'get'|'post'|'put'|'delete'  $method
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $body
     * @return Fluent<array-key, mixed>
     *
     * @internal
     */
    public function request(string $method, string $path, string $action, array $query = [], array $body = []): Fluent
    {
        $response = $this->send($this->http()->withToken($this->token()), $method, $path, $query, $body, $action);

        return new Fluent($response->fluent()->array('data'));
    }

    protected function token(): string
    {
        return Cache::remember($this->tokenCacheKey(), self::TOKEN_TTL, $this->login(...));
    }

    protected function login(): string
    {
        $username = $this->username ?: throw MissingConfiguration::key('username', 'OPENPROVIDER_USERNAME');
        $password = $this->password ?: throw MissingConfiguration::key('password', 'OPENPROVIDER_PASSWORD');

        $response = $this->send($this->http(), 'post', 'auth/login', [], [
            'username' => $username,
            'password' => $password,
            'ip' => $this->ip,
        ], 'log in');

        $token = $response->fluent()->string('data.token')->value();

        return $token !== '' ? $token : throw RequestFailed::unexpected('log in', 'token', $response->fluent()->toArray());
    }

    /**
     * One cached token per account, so apps that switch between accounts or
     * environments never send one account's token to another.
     */
    protected function tokenCacheKey(): string
    {
        return 'openprovider.token.'.hash('sha256', "{$this->baseUrl}|{$this->username}|{$this->ip}");
    }

    protected function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)->acceptJson()->asJson();
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $body
     */
    protected function send(PendingRequest $http, string $method, string $path, array $query, array $body, string $action): Response
    {
        try {
            $response = $http
                ->withQueryParameters(array_map(fn (mixed $value) => is_bool($value) ? ($value ? 'true' : 'false') : $value, $query))
                ->send(Str::upper($method), $path, $body === [] ? [] : ['json' => $body]);
        } catch (ConnectionException $e) {
            throw ConnectionFailed::from($e);
        }

        if ($response->failed()) {
            throw RequestFailed::fromResponse($response, $action);
        }

        return $response;
    }
}
