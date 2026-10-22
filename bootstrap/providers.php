<?php

use App\Modules\Accounting\AccountingServiceProvider;
use App\Modules\Appointments\AppointmentsServiceProvider;
use App\Modules\HR\HumanResourcesServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Payments\PaymentsServiceProvider;
use App\Modules\Pharmacy\PharmacyServiceProvider;
use App\Modules\Reference\ReferenceDataServiceProvider;
use App\Modules\Transactions\TransactionsServiceProvider;
use App\Providers\AppServiceProvider;
use App\Shared\SharedServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    ReferenceDataServiceProvider::class,
    IdentityServiceProvider::class,
    AccountingServiceProvider::class,
    HumanResourcesServiceProvider::class,
    TransactionsServiceProvider::class,
    PaymentsServiceProvider::class,
    PharmacyServiceProvider::class,
    AppointmentsServiceProvider::class,
];
