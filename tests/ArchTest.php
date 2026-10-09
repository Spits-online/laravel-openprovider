<?php

declare(strict_types=1);

arch()->preset()->php();
arch()->preset()->security();

arch('every file declares strict types')
    ->expect('SpitsOnline\Openprovider')
    ->toUseStrictTypes();

arch('data objects are final and readonly')
    ->expect('SpitsOnline\Openprovider\Data')
    ->classes()
    ->toBeFinal()
    ->toBeReadonly();

arch('exceptions extend the package base exception')
    ->expect('SpitsOnline\Openprovider\Exceptions')
    ->classes()
    ->toExtend('SpitsOnline\Openprovider\Exceptions\OpenproviderException')
    ->ignoring('SpitsOnline\Openprovider\Exceptions\OpenproviderException');

arch('contracts are interfaces')->expect('SpitsOnline\Openprovider\Contracts')->toBeInterfaces();
arch('concerns are traits')->expect('SpitsOnline\Openprovider\Concerns')->toBeTraits();
arch('enums are enums')->expect('SpitsOnline\Openprovider\Enums')->toBeEnums();
