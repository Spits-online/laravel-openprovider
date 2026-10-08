<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Http\Controllers;

use Maatwebsite\Excel\Facades\Excel;
use SpitsOnline\Openprovider\Exceptions\MissingDependency;
use SpitsOnline\Openprovider\Exports\ZoneExport;
use SpitsOnline\Openprovider\Openprovider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The opt-in export routes. See `exports.enabled` in `config/openprovider.php`.
 */
class ExportController
{
    public function __construct(
        protected Openprovider $openprovider,
    ) {}

    public function zone(string $domain): BinaryFileResponse
    {
        if (! class_exists(Excel::class)) {
            throw MissingDependency::package('maatwebsite/excel', 'export a DNS zone');
        }

        return Excel::download(new ZoneExport($this->openprovider->zones()->find($domain)->records), "dns_zone_{$domain}.xlsx");
    }
}
