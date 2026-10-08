<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
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

    public function zone(Request $request, string $domain): BinaryFileResponse
    {
        if (! class_exists(Excel::class)) {
            throw MissingDependency::package('maatwebsite/excel', 'export a DNS zone');
        }

        $filename = $this->filename($request, "dns_zone_{$domain}");

        return Excel::download(new ZoneExport($this->openprovider->zones()->find($domain)->records), $filename);
    }

    /**
     * The download's name: the request's `filename`, or the default, always
     * ending in `.xlsx`. Only letters, digits, spaces, dots, dashes and
     * underscores are allowed, so the name can't leave the download header.
     */
    protected function filename(Request $request, string $default): string
    {
        $input = new Fluent(Validator::validate($request->all(), [
            'filename' => ['nullable', 'string', 'max:200', 'regex:/^[\w\-. ]+$/D'],
        ]));

        return Str::finish($input->string('filename')->value() ?: $default, '.xlsx');
    }
}
