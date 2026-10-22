<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Doctors\Contracts;

use App\Modules\Appointments\Doctors\Data\SpecialtyData;
use App\Modules\Appointments\Doctors\Models\Specialty;
use App\Shared\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SpecialtyServiceInterface
{
    /**
     * @return LengthAwarePaginator<int, Specialty>|CursorPaginator<int, Specialty>
     */
    public function listSpecialties(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    public function findSpecialty(string $id): Specialty;

    public function findWithDeletedSpecialty(string $id): Specialty;

    /** Unique code. */
    public function createSpecialty(SpecialtyData $data): Specialty;

    public function updateSpecialty(Specialty $specialty, SpecialtyData $data): Specialty;

    /** Soft delete; assigned to doctors or services → SpecialtyInUseException. */
    public function deleteSpecialty(Specialty $specialty): void;

    public function archiveSpecialty(Specialty $specialty): Specialty;

    public function unarchiveSpecialty(Specialty $specialty): Specialty;

    public function restoreSpecialty(Specialty $specialty): Specialty;
}
