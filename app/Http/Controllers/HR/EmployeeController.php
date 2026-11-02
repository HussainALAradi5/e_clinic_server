<?php

declare(strict_types=1);

namespace App\Http\Controllers\HR;

use App\Enums\HR\EmployeeStatus;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\HR\ChangeEmployeeStatusRequest;
use App\Http\Requests\HR\EmployeeRequest;
use App\Http\Requests\HR\SalaryComponentRequest;
use App\Http\Requests\HR\TerminateEmployeeRequest;
use App\Http\Resources\HR\EmployeeResource;
use App\Http\Resources\HR\SalaryComponentResource;
use App\Interfaces\HR\EmployeeServiceInterface;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeSalaryComponent;
use App\Utilities\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class EmployeeController
{
    use ApiResponse;

    public function __construct(private readonly EmployeeServiceInterface $employees) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, Employee::listQueryConfig());

        return $this->paginated(EmployeeResource::collection($this->employees->list($query)), $query);
    }

    public function show(string $employee): JsonResponse
    {
        return $this->ok(new EmployeeResource($this->employees->find($employee)));
    }

    public function store(EmployeeRequest $request): JsonResponse
    {
        return $this->created(new EmployeeResource($this->employees->create($request->toData())));
    }

    public function update(EmployeeRequest $request, string $employee): JsonResponse
    {
        $model = $this->employees->find($employee);

        return $this->ok(new EmployeeResource($this->employees->update($model, $request->toData(), $request->integer('version'))));
    }

    public function terminate(TerminateEmployeeRequest $request, string $employee): JsonResponse
    {
        return $this->ok(new EmployeeResource($this->employees->terminate($this->employees->find($employee), $request->toData())));
    }

    public function changeStatus(ChangeEmployeeStatusRequest $request, string $employee): JsonResponse
    {
        $status = EmployeeStatus::from($request->string('status')->toString());

        return $this->ok(new EmployeeResource($this->employees->changeStatus($this->employees->find($employee), $status, $request->string('reason')->toString())));
    }

    public function addSalaryComponent(SalaryComponentRequest $request, string $employee): JsonResponse
    {
        return $this->created(new SalaryComponentResource($this->employees->addSalaryComponent($this->employees->find($employee), $request->toData())));
    }

    public function removeSalaryComponent(string $component): Response
    {
        $model = EmployeeSalaryComponent::query()->findOrFail($component);
        $this->employees->find($model->employee_id); // 404 outside the caller's scope
        $this->employees->removeSalaryComponent($model);

        return $this->noContent();
    }
}
