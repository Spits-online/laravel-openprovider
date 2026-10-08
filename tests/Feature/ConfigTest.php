<?php

declare(strict_types=1);

use SpitsOnline\Openprovider\OpenproviderServiceProvider;

it('merges an app config over the defaults key by key', function () {
    // What an app's config/openprovider.php with only these keys leaves in the repository.
    config()->set('openprovider', ['routes' => ['enabled' => true]]);

    (new OpenproviderServiceProvider(app()))->register();

    expect(config('openprovider.routes'))->toBe(['enabled' => true, 'prefix' => '', 'middleware' => ['web', 'auth']])
        ->and(config('openprovider.base_url'))->toBe('https://api.openprovider.eu/v1beta');
});

it('replaces lists instead of appending to them', function () {
    config()->set('openprovider', ['routes' => ['middleware' => ['api']]]);

    (new OpenproviderServiceProvider(app()))->register();

    expect(config('openprovider.routes.middleware'))->toBe(['api']);
});
