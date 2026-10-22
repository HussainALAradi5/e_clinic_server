<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Medications\Contracts;

use App\Modules\Pharmacy\Medications\Data\MedicationData;
use App\Modules\Pharmacy\Medications\Models\Medication;
use App\Shared\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Medication catalogue. Each medication owns a price-list service (category `pharmacy`): one price list for billing.
 */
interface MedicationServiceInterface
{
    /**
     * Search by name / code / GTIN, filter by form, manufacturer, origin; rows carry `stock_on_hand`.
     *
     * @return LengthAwarePaginator<int, Medication>|CursorPaginator<int, Medication>
     */
    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    /** Medication with manufacturer, origin, price and stock. */
    public function find(string $id): Medication;

    public function findWithDeleted(string $id): Medication;

    /** Resolves a scanned manufacturer barcode (GTIN). */
    public function findByBarcode(string $gtin): Medication;

    /** Adds the medication and its price-list service in one transaction. */
    public function create(MedicationData $data): Medication;

    /** Updates the medication; price / VAT / name changes go to its price-list service. */
    public function update(Medication $medication, MedicationData $data): Medication;

    /**
     * Active medications with a reorder level whose dispensable stock is at or below it.
     *
     * @return Collection<int, Medication>
     */
    public function lowStock(): Collection;

    /** Soft delete; one with stock or prescription history → InvalidStateTransitionException (archive it). */
    public function delete(Medication $medication): void;

    /** Hidden from pickers (its price-list service too); history and stock stay. */
    public function archive(Medication $medication): Medication;

    public function unarchive(Medication $medication): Medication;

    public function restore(Medication $medication): Medication;
}
