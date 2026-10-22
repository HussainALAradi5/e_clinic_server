<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

use App\Shared\Data\ChargeLine;

/**
 * Something the patient pays for (a completed visit or session, a clinical order, a dispensation). It is billed
 * through the single billing path InvoiceService::charge(), which adds its lines to the patient's open draft.
 */
interface Chargeable
{
    public function chargePatientId(): string;

    /**
     * Price-list items to bill (prices, VAT and revenue accounts are read from the price list at charge time).
     *
     * @return list<ChargeLine>
     */
    public function chargeLines(): array;
}
