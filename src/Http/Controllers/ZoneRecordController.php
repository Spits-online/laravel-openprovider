<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Response as ResponseFactory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Fluent;
use Illuminate\Validation\Rule;
use SpitsOnline\Openprovider\Data\Record;
use SpitsOnline\Openprovider\Enums\Provider;
use SpitsOnline\Openprovider\Enums\RecordType;
use SpitsOnline\Openprovider\Enums\Ttl;
use SpitsOnline\Openprovider\Openprovider;
use SpitsOnline\Openprovider\Resources\ZoneResource;

/**
 * The opt-in DNS record routes. See `routes.enabled` in `config/openprovider.php`.
 */
class ZoneRecordController
{
    public function __construct(
        protected Openprovider $openprovider,
    ) {}

    public function show(Request $request, string $domain): JsonResponse
    {
        $input = $this->validate($request, []);

        return ResponseFactory::json(['data' => $this->zone($domain, $input)->get()->toArray()]);
    }

    public function store(Request $request, string $domain): Response
    {
        $input = $this->validate($request, $this->recordRules('record'));

        $this->zone($domain, $input)->records()->add(Record::fromArray($input->array('record')));

        return ResponseFactory::noContent();
    }

    public function update(Request $request, string $domain): Response
    {
        $input = $this->validate($request, [
            ...$this->recordRules('original_record', existing: true),
            ...$this->recordRules('record'),
        ]);

        $this->zone($domain, $input)->records()->update(
            Record::fromArray($input->array('original_record')),
            Record::fromArray($input->array('record')),
        );

        return ResponseFactory::noContent();
    }

    public function destroy(Request $request, string $domain): Response
    {
        $input = $this->validate($request, [
            'records' => ['required', 'array', 'min:1'],
            ...$this->recordRules('records.*', existing: true),
        ]);

        $this->zone($domain, $input)->records()->delete(...Record::listFrom($input->array('records')));

        return ResponseFactory::noContent();
    }

    /**
     * Validate the request, which may name the provider of a premium DNS zone.
     *
     * @param  array<string, list<mixed>>  $rules
     * @return Fluent<array-key, mixed>
     */
    protected function validate(Request $request, array $rules): Fluent
    {
        return new Fluent(Validator::validate($request->all(), ['provider' => ['nullable', Rule::enum(Provider::class)], ...$rules]));
    }

    /**
     * @param  Fluent<array-key, mixed>  $input
     */
    protected function zone(string $domain, Fluent $input): ZoneResource
    {
        $zone = $this->openprovider->zone($domain);
        $provider = $input->enum('provider', Provider::class);

        return $provider === null ? $zone : $zone->provider($provider);
    }

    /**
     * The rules for one record under `$key`. A record that already exists may carry
     * any TTL Openprovider stored; a new one must use a TTL Openprovider accepts.
     *
     * @return array<string, list<mixed>>
     */
    protected function recordRules(string $key, bool $existing = false): array
    {
        return [
            $key => ['required', 'array'],
            "{$key}.name" => ['nullable', 'string'],
            "{$key}.type" => ['required', Rule::enum(RecordType::class)->except([RecordType::SOA])],
            "{$key}.value" => ['required', 'string'],
            "{$key}.ttl" => ['required', $existing ? 'integer' : Rule::enum(Ttl::class)],
            "{$key}.prio" => ['nullable', 'integer', "required_if:{$key}.type,".RecordType::MX->value],
        ];
    }
}
