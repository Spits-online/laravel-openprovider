<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    |
    | The Openprovider account the API logs in with. The account needs API
    | access enabled in the Openprovider control panel. The IP address is the
    | one of the server that makes the requests, sent along with the login.
    |
    */

    'username' => env('OPENPROVIDER_USERNAME'),
    'password' => env('OPENPROVIDER_PASSWORD'),
    'ip' => env('OPENPROVIDER_IP'),

    /*
    |--------------------------------------------------------------------------
    | API
    |--------------------------------------------------------------------------
    |
    | Point this at Openprovider's sandbox to test without buying anything:
    | http://api.sandbox.openprovider.nl:8480/v1beta
    |
    */

    'base_url' => env('OPENPROVIDER_BASE_URL', 'https://api.openprovider.eu/v1beta'),

    /*
    |--------------------------------------------------------------------------
    | DNS record routes
    |--------------------------------------------------------------------------
    |
    | JSON endpoints to read, add, change, remove and export the records of a
    | DNS zone, for apps that manage DNS from their own front end. They are off
    | until an app enables them, and run behind `auth` by default, because
    | they change live DNS.
    |
    | See https://github.com/Spits-online/laravel-openprovider#dns-record-routes
    |
    */

    'routes' => [
        'enabled' => false,
        'prefix' => '',
        'middleware' => ['web', 'auth'],
    ],

];
