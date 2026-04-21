<?php

namespace Spits\LaravelOpenproviderApi\Http\Controllers;

use Maatwebsite\Excel\Facades\Excel;
use Spits\LaravelOpenproviderApi\Http\Exports\DnsZoneExport;
use Spits\LaravelOpenproviderApi\Http\Requests\DnsRecordDestroyRequest;
use Spits\LaravelOpenproviderApi\Http\Requests\DnsRecordStoreRequest;
use Spits\LaravelOpenproviderApi\Http\Requests\DnsRecordUpdateRequest;
use Spits\LaravelOpenproviderApi\Services\DnsService;

class DnsRecordsController
{
    public function show(string $domain, DnsService $service)
    {
        $options = request()->input('options', ['with_records' => 'true']);

        $response = $service->getDnsZone($domain, $options);

        return $response->collect();
    }

    public function store(string $domain, DnsService $service, DnsRecordStoreRequest $request)
    {
        $data = [
            'provider' => $request->validated('provider'),
            'records' => [
                'add' => [
                    $request->validated('record'),
                ],
            ],
        ];

        return $service->updateDnsZone($domain, $data);
    }

    public function update(string $domain, DnsService $service, DnsRecordUpdateRequest $request)
    {
        $data = [
            'provider' => $request->validated('provider'),
            'records' => [
                'update' => [
                    [
                        'original_record' => $request->validated('original_record'),
                        'record' => $request->validated('record'),
                    ],
                ],
            ],
        ];

        return $service->updateDnsZone($domain, $data);
    }

    public function destroy(string $domain, DnsService $service, DnsRecordDestroyRequest $request)
    {
        $data = [
            'provider' => $request->validated('provider'),
            'records' => [
                'remove' => $request->validated('records'),
            ],
        ];

        return $service->updateDnsZone($domain, $data);
    }

    public function export(string $domain, DnsService $service)
    {
        $options = request()->input('options', ['with_records' => 'true']);

        $response = $service->getDnsZone($domain, $options);
        $data = $response->collect();
        $collection = collect($data['data']['records'])->map(function ($item) {
            return (object) $item;
        });

        $export = new DnsZoneExport($collection);

        return Excel::download($export, 'dns_zone_'.$domain.'.xlsx');
    }
}
