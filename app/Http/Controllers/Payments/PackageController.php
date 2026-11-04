<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments;

use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Payments\ServicePackageRequest;
use App\Http\Resources\Payments\PatientPackageResource;
use App\Http\Resources\Payments\ServicePackageResource;
use App\Interfaces\Appointments\PatientServiceInterface;
use App\Interfaces\Payments\PackageServiceInterface;
use App\Models\Payments\PatientPackage;
use App\Models\Payments\ServicePackage;
use App\Utilities\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Packages: the catalog (prepaid sessions of one service) and the packages patients bought.
 */
final class PackageController
{
    use ApiResponse;

    public function __construct(
        private readonly PackageServiceInterface $packages,
        private readonly PatientServiceInterface $patients,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, ServicePackage::listQueryConfig());

        return $this->paginated(ServicePackageResource::collection($this->packages->listCatalog($query)), $query);
    }

    public function show(string $package): JsonResponse
    {
        return $this->ok(new ServicePackageResource($this->packages->findPackage($package)));
    }

    public function store(ServicePackageRequest $request): JsonResponse
    {
        return $this->created(new ServicePackageResource($this->packages->create($request->toData())));
    }

    public function update(ServicePackageRequest $request, string $package): JsonResponse
    {
        return $this->ok(new ServicePackageResource($this->packages->update($this->packages->findPackage($package), $request->toData())));
    }

    /** Packages patients bought (patients: their own), filter by patient or status. */
    public function sold(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, PatientPackage::listQueryConfig());

        return $this->paginated(PatientPackageResource::collection($this->packages->listSold($query)), $query);
    }

    /** Sells a package to the patient: a line on their draft invoice; it activates once that invoice is paid. */
    public function sell(Request $request, string $patient): JsonResponse
    {
        $request->validate(['package_id' => ['required', 'uuid']]);
        $sold = $this->packages->sell(
            $this->patients->find($patient),
            $this->packages->findPackage($request->string('package_id')->toString()),
            (string) $request->header('Idempotency-Key'),
        );

        return $this->created(new PatientPackageResource($sold));
    }
}
