<?php

use App\Modules\Identity\Users\Models\Person;
use App\Modules\Reference\Companies\Models\Company;

return [

    /*
    |--------------------------------------------------------------------------
    | Reference Data
    |--------------------------------------------------------------------------
    */

    /* Records that own an address (model => access resource): the address is readable when its owner is. */
    'address_owners' => [
        Person::class => 'people',
        Company::class => 'companies',
    ],

    /*
    | Tables that reference a company: a referenced company cannot be deleted (archive it instead).
    | Modules add theirs: medications.manufacturer_id, stock_batches.supplier_id, patients.insurer_id, expenses.payee_company_id …
    */
    'company_usages' => [],

];
