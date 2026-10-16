<?php

use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Reference\ReferenceDataServiceProvider;
use App\Providers\AppServiceProvider;
use App\Shared\SharedServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    ReferenceDataServiceProvider::class,
    IdentityServiceProvider::class,
];
