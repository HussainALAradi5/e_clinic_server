<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboards;

use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Dashboards\DashboardDataRequest;
use App\Http\Requests\Dashboards\DashboardMetricsRequest;
use App\Http\Requests\Dashboards\DashboardRequest;
use App\Http\Resources\Dashboards\DashboardDataResource;
use App\Http\Resources\Dashboards\DashboardMetricResource;
use App\Http\Resources\Dashboards\DashboardResource;
use App\Interfaces\Dashboards\DashboardServiceInterface;
use App\Models\Dashboards\Dashboard;
use App\Utilities\Query\ListQuery;
use App\Utilities\Shared\ClinicClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Saved dashboards of any section, their figures, and the metrics a section offers. */
final class DashboardController
{
    use ApiResponse;

    public function __construct(private readonly DashboardServiceInterface $dashboards) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, Dashboard::listQueryConfig());

        return $this->paginated(DashboardResource::collection($this->dashboards->list($query)), $query);
    }

    public function show(string $dashboard): JsonResponse
    {
        return $this->ok(new DashboardResource($this->dashboards->findDashboard($dashboard)));
    }

    public function store(DashboardRequest $request): JsonResponse
    {
        return $this->created(new DashboardResource($this->dashboards->createDashboard($request->toData())));
    }

    public function update(DashboardRequest $request, string $dashboard): JsonResponse
    {
        return $this->ok(new DashboardResource($this->dashboards->updateDashboard($this->dashboards->findDashboard($dashboard), $request->toData())));
    }

    public function destroy(string $dashboard): Response
    {
        $this->dashboards->deleteDashboard($this->dashboards->findDashboard($dashboard));

        return $this->noContent();
    }

    public function setDefault(string $dashboard): JsonResponse
    {
        return $this->ok(new DashboardResource($this->dashboards->setDefault($this->dashboards->findDashboard($dashboard))));
    }

    public function data(DashboardDataRequest $request, ClinicClock $clock, string $dashboard): JsonResponse
    {
        return $this->ok(new DashboardDataResource($this->dashboards->data($this->dashboards->findDashboard($dashboard), $request->toFilter($clock))));
    }

    public function metrics(DashboardMetricsRequest $request): JsonResponse
    {
        return $this->ok(DashboardMetricResource::collection($this->dashboards->metrics($request->section())));
    }
}
