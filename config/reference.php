<?php

use App\Models\Identity\Person;
use App\Models\Reference\Company;

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
    'company_usages' => [
        'insured_patients' => ['table' => 'patients', 'column' => 'insurance_company_id'],
    ],

];
