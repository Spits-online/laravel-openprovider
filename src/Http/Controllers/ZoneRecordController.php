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
use SpitsOnline\Openprovider\Enums\RecordType;
use SpitsOnline\Openprovider\Enums\Ttl;
use SpitsOnline\Openprovider\Openprovider;

/**
 * The opt-in DNS record routes. See `routes.enabled` in `config/openprovider.php`.
 */
class ZoneRecordController
{
    public function __construct(
        protected Openprovider $openprovider,
    ) {}

    public function show(string $domain): JsonResponse
    {
        return ResponseFactory::json(['data' => $this->openprovider->zones()->find($domain)->toArray()]);
    }

    public function store(Request $request, string $domain): Response
    {
        $input = $this->validate($request, $this->recordRules('record'));

        $this->openprovider->zones()->addRecords($domain, [Record::fromArray($input->array('record'))], $this->provider($input));

        return ResponseFactory::noContent();
    }

    public function update(Request $request, string $domain): Response
    {
        $input = $this->validate($request, [
            ...$this->recordRules('original_record', existing: true),
            ...$this->recordRules('record'),
        ]);

        $this->openprovider->zones()->updateRecord(
            $domain,
            Record::fromArray($input->array('original_record')),
            Record::fromArray($input->array('record')),
            $this->provider($input),
        );

        return ResponseFactory::noContent();
    }

    public function destroy(Request $request, string $domain): Response
    {
        $input = $this->validate($request, [
            'records' => ['required', 'array', 'min:1'],
            ...$this->recordRules('records.*', existing: true),
        ]);

        $this->openprovider->zones()->removeRecords($domain, Record::listFrom($input->array('records')), $this->provider($input));

        return ResponseFactory::noContent();
    }

    /**
     * Validate the request, which may name the DNS provider of a premium zone.
     *
     * @param  array<string, list<mixed>>  $rules
     * @return Fluent<array-key, mixed>
     */
    protected function validate(Request $request, array $rules): Fluent
    {
        return new Fluent(Validator::validate($request->all(), ['provider' => ['nullable', 'string'], ...$rules]));
    }

    /**
     * @param  Fluent<array-key, mixed>  $input
     */
    protected function provider(Fluent $input): ?string
    {
        return $input->string('provider')->value() ?: null;
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
