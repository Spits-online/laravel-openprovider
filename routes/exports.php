<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use SpitsOnline\Openprovider\Http\Controllers\ExportController;

Route::controller(ExportController::class)->group(function () {
    Route::get('dns-zone/export/records/{domain}', 'zone')->name('dns-zone.export');
});
