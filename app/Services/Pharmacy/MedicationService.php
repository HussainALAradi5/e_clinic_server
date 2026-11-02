<?php

declare(strict_types=1);

namespace App\Services\Pharmacy;

use App\Dtos\Pharmacy\MedicationData;
use App\Enums\Payments\ServiceCategory;
use App\Exceptions\Payments\ServiceCodeTakenException;
use App\Exceptions\Shared\InvalidStateTransitionException;
use App\Interfaces\Pharmacy\MedicationServiceInterface;
use App\Models\Accounting\Account;
use App\Models\Payments\BillableService;
use App\Models\Pharmacy\Medication;
use App\Models\Pharmacy\MedicationBatch;
use App\Models\Pharmacy\PrescriptionItem;
use App\Services\Base\BaseEntityService;
use App\Utilities\Access\OwnershipScope;
use App\Utilities\Query\ApplyListQuery;
use App\Utilities\Shared\ClinicClock;
use App\Utilities\Shared\GenericEntityScopes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseEntityService<Medication>
 */
final class MedicationService extends BaseEntityService implements MedicationServiceInterface
{
    public const RELATIONS = ['billableService', 'manufacturer', 'originCountry'];

    public function __construct(ApplyListQuery $listQuery, private readonly ClinicClock $clock)
    {
        parent::__construct($listQuery);
    }

    public function find(string $id): Medication
    {
        return parent::find($id);
    }

    public function findWithDeleted(string $id): Medication
    {
        return parent::findWithDeleted($id);
    }

    public function findByBarcode(string $gtin): Medication
    {
        return $this->query()->where('gtin', $gtin)->firstOrFail();
    }

    public function create(MedicationData $data): Medication
    {
        $medication = DB::transaction(function () use ($data): Medication {
            $medication = new Medication($data->values);
            $this->assertServiceCodeFree($medication->code);

            $service = BillableService::query()->create([
                'code' => $medication->code,
                'name' => $medication->displayName(),
                'name_ar' => $medication->name_ar,
                'category' => ServiceCategory::Pharmacy,
                'price' => $data->price ?? '0',
                'tax_rate' => $data->taxRate ?? '0',
                'revenue_account_id' => Account::query()->where('code', Config::string('pharmacy.revenue_account'))->valueOrFail('id'),
                'is_insurable' => true,
            ]);
            $medication->billable_service_id = $service->id;
            $medication->save();

            return $medication;
        });

        return $this->find($medication->id);
    }

    public function update(Medication $medication, MedicationData $data): Medication
    {
        DB::transaction(function () use ($medication, $data): void {
            $medication->fill($data->values);
            $service = BillableService::query()->withDeleted()->lockForUpdate()->findOrFail($medication->billable_service_id);
            if ($medication->isDirty('code')) {
                $this->assertServiceCodeFree($medication->code, $service->id);
                $service->code = $medication->code;
            }
            $medication->save();

            // One price list: name, price and VAT live on the service (new charges use them; issued invoices keep theirs).
            $service->fill(array_filter([
                'name' => $medication->displayName(),
                'name_ar' => $medication->name_ar,
                'price' => $data->price,
                'tax_rate' => $data->taxRate,
            ], fn (mixed $v): bool => $v !== null))->save();
        });

        return $this->find($medication->id);
    }

    public function lowStock(): Collection
    {
        $withStock = Medication::query()->withStock($this->clock->today())->where('medications.reorder_level', '>', 0);

        /** @var Collection<int, Medication> $low */
        $low = OwnershipScope::apply(
            GenericEntityScopes::notArchived(Medication::query()->fromSub($withStock, 'medications')->with(self::RELATIONS)),
            'medications',
            Auth::user(),
        )
            ->whereColumn('stock_on_hand', '<=', 'reorder_level')
            ->orderBy('generic_name')
            ->get();

        return $low;
    }

    public function delete(Model $model): void
    {
        DB::transaction(function () use ($model): void {
            parent::delete($model);
            $this->service($model)?->archive();
        });
    }

    public function archive(Model $model): Medication
    {
        /** @var Medication $model */
        if ($model->isArchived()) {
            return $model;
        }

        return DB::transaction(function () use ($model): Medication {
            $this->service($model)?->archive();

            return $this->find(parent::archive($model)->id);
        });
    }

    public function unarchive(Model $model): Medication
    {
        /** @var Medication $model */
        if (! $model->isArchived()) {
            return $model;
        }

        return DB::transaction(function () use ($model): Medication {
            $this->service($model)?->unarchive();

            return $this->find(parent::unarchive($model)->id);
        });
    }

    public function restore(Model $model): Medication
    {
        /** @var Medication $model */
        if (! $model->isDeleted()) {
            return $model;
        }

        return DB::transaction(function () use ($model): Medication {
            parent::restore($model);
            if (! $model->isArchived()) {
                $this->service($model)?->unarchive();
            }

            return $this->find($model->id);
        });
    }

    /**
     * @return Builder<Medication>
     */
    protected function query(): Builder
    {
        return OwnershipScope::apply(Medication::query()->withStock($this->clock->today())->with(self::RELATIONS), 'medications', Auth::user());
    }

    /** Medicines with stock or prescription history are kept (archive them instead). */
    protected function beforeDelete(Model $model): void
    {
        /** @var Medication $model */
        $used = MedicationBatch::query()->withDeleted()->where('medication_id', $model->id)->exists()
            || PrescriptionItem::query()->where('medication_id', $model->id)->exists();
        if ($used) {
            throw new InvalidStateTransitionException(context: ['from' => 'active', 'to' => 'deleted', 'reason' => 'has_history']);
        }
    }

    private function service(Medication $medication): ?BillableService
    {
        return BillableService::query()->withDeleted()->find($medication->billable_service_id);
    }

    private function assertServiceCodeFree(string $code, ?string $exceptServiceId = null): void
    {
        $taken = GenericEntityScopes::withDeleted(BillableService::query())->where('code', $code)
            ->when($exceptServiceId !== null, fn (Builder $q) => $q->whereKeyNot($exceptServiceId))
            ->exists();
        if ($taken) {
            throw new ServiceCodeTakenException;
        }
    }
}
