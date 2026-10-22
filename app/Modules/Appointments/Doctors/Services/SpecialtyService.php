<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Doctors\Services;

use App\Modules\Appointments\Doctors\Contracts\SpecialtyServiceInterface;
use App\Modules\Appointments\Doctors\Data\SpecialtyData;
use App\Modules\Appointments\Doctors\Exceptions\SpecialtyInUseException;
use App\Modules\Appointments\Doctors\Models\Doctor;
use App\Modules\Appointments\Doctors\Models\Specialty;
use App\Modules\Payments\Catalog\Models\BillableService;
use App\Shared\Query\ApplyListQuery;
use App\Shared\Query\ListQuery;
use App\Shared\Services\BaseEntityService;
use App\Shared\Support\GenericEntityScopes;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * @extends BaseEntityService<Specialty>
 */
final class SpecialtyService extends BaseEntityService implements SpecialtyServiceInterface
{
    public function __construct(ApplyListQuery $listQuery)
    {
        parent::__construct($listQuery);
    }

    public function listSpecialties(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        return $this->list($query);
    }

    public function findSpecialty(string $id): Specialty
    {
        return $this->find($id);
    }

    public function findWithDeletedSpecialty(string $id): Specialty
    {
        return $this->findWithDeleted($id);
    }

    public function createSpecialty(SpecialtyData $data): Specialty
    {
        $this->assertCodeFree((string) $data->values['code']);

        return $this->findSpecialty(Specialty::query()->create($data->values)->id);
    }

    public function updateSpecialty(Specialty $specialty, SpecialtyData $data): Specialty
    {
        $specialty->fill($data->values);
        if ($specialty->isDirty('code')) {
            $this->assertCodeFree($specialty->code, $specialty->id);
        }
        $specialty->save();

        return $this->findSpecialty($specialty->id);
    }

    public function deleteSpecialty(Specialty $specialty): void
    {
        $this->delete($specialty);
    }

    public function archiveSpecialty(Specialty $specialty): Specialty
    {
        return $specialty->isArchived() ? $specialty : $this->archive($specialty);
    }

    public function unarchiveSpecialty(Specialty $specialty): Specialty
    {
        return $specialty->isArchived() ? $this->unarchive($specialty) : $specialty;
    }

    public function restoreSpecialty(Specialty $specialty): Specialty
    {
        return $specialty->isDeleted() ? $this->restore($specialty) : $specialty;
    }

    public function restore(Model $model): Model
    {
        /** @var Specialty $model */
        $this->assertCodeFree($model->code, $model->id);

        return parent::restore($model);
    }

    /**
     * @return Builder<Specialty>
     */
    protected function query(): Builder
    {
        // Every signed-in user reads specialties (booking, price list, portal); changes need specialties.* rights.
        return Specialty::query()->withCount('doctors');
    }

    protected function beforeDelete(Model $model): void
    {
        if (Doctor::query()->where('specialty_id', $model->getKey())->exists() || BillableService::query()->where('specialty_id', $model->getKey())->exists()) {
            throw new SpecialtyInUseException;
        }
    }

    private function assertCodeFree(string $code, ?string $exceptId = null): void
    {
        $taken = GenericEntityScopes::withDeleted(Specialty::query())->where('code', $code)
            ->when($exceptId !== null, fn (Builder $q) => $q->whereKeyNot($exceptId))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['code' => [__('validation.unique', ['attribute' => 'code'])]]);
        }
    }
}
