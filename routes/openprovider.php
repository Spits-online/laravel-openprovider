<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use SpitsOnline\Openprovider\Http\Controllers\ZoneRecordController;

Route::controller(ZoneRecordController::class)->group(function () {
    Route::get('dns-zone/records/{domain}', 'show')->name('dns-zone.records.show');
    Route::post('dns-zone/records/{domain}', 'store')->name('dns-zone.records.store');
    Route::put('dns-zone/records/{domain}', 'update')->name('dns-zone.records.update');
    Route::delete('dns-zone/records/{domain}', 'destroy')->name('dns-zone.records.destroy');
    Route::get('dns-zone/export/records/{domain}', 'export')->name('dns-zone.export');
});
