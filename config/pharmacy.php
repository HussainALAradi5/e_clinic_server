<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pharmacy
    |--------------------------------------------------------------------------
    */

    /* Revenue account (chart code) of the price-list service created with each medication. */
    'revenue_account' => '4500',

    /* Default validity of a prescription (days from the day it is written). */
    'prescription_valid_days' => 30,

    /* Batches expiring within this many days are flagged "expiring soon" on the stock screen. */
    'near_expiry_days' => 90,

    /* Stock corrections above this many units (or of controlled drugs) need another pharmacist's approval. */
    'adjustment_approval_threshold' => 10,

];
