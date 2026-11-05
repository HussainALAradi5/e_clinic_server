<?php

// Error messages by error code — Pharmacy.

return [
    'DUPLICATE_THERAPY' => 'The patient already has an open prescription for :medications. Confirm to prescribe it again.',
    'ALLERGY_WARNING' => 'The patient\'s recorded allergies mention :medications. Confirm to prescribe anyway.',
    'PRESCRIPTION_NOT_DISPENSABLE' => 'This prescription can no longer be dispensed (cancelled, completed or expired).',
    'DISPENSE_QUANTITY_EXCEEDED' => 'Only :remaining of :medication is left on the prescription.',
    'MEDICATION_REQUIRES_PRESCRIPTION' => ':medication can only be dispensed against a prescription.',
    'CONTROLLED_DRUG_RULE' => ':medication is a controlled drug: a second staff member must witness it, and take-home supply needs a prescription.',
    'ADMINISTRATION_REQUIRES_SESSION' => 'Medicines can only be recorded on a checked-in or in-progress appointment.',
    'INSUFFICIENT_STOCK' => 'Not enough :medication in the :place (:available available).',
    'STOCK_SAME_LOCATION' => 'The lot is already there — choose the other place.',
    'BATCH_EXPIRED' => 'Lot :batch cannot be used: it is expired, quarantined or of another medicine.',
    'STOCK_ABOVE_RECEIVED' => 'A lot cannot hold more than was received (:received).',
    'ADJUSTMENT_NEEDS_APPROVAL' => 'Corrections above :limit units, or of controlled drugs, must be approved by another pharmacist.',
];
