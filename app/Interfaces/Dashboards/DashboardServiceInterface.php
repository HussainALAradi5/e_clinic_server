<?php

declare(strict_types=1);

namespace App\Interfaces\Dashboards;

use App\Dtos\Dashboards\DashboardData;
use App\Dtos\Dashboards\DashboardDataResult;
use App\Dtos\Dashboards\DashboardFilterDto;
use App\Dtos\Dashboards\DashboardMetric;
use App\Enums\Dashboards\DashboardSection;
use App\Models\Dashboards\Dashboard;
use App\Utilities\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Saved dashboards of any section: which widgets, in what order and size. Figures come from the section's metric
 * provider, and each user only receives the widgets their permissions allow.
 */
interface DashboardServiceInterface
{
    /** @return LengthAwarePaginator<int, Dashboard>|CursorPaginator<int, Dashboard> */
    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    public function findDashboard(string $id): Dashboard;

    /** With `is_default`, it replaces the section's current default. */
    public function createDashboard(DashboardData $data): Dashboard;

    /** Name, description and widgets; the section and the default flag do not change here. */
    public function updateDashboard(Dashboard $dashboard, DashboardData $data): Dashboard;

    /** Soft delete; the section's default cannot be deleted (`DASHBOARD_IS_DEFAULT`). */
    public function deleteDashboard(Dashboard $dashboard): void;

    /** Makes it the only default of its section. */
    public function setDefault(Dashboard $dashboard): Dashboard;

    /** The dashboard's widgets for the filter, leaving out metrics the signed-in user may not see. */
    public function data(Dashboard $dashboard, DashboardFilterDto $filter): DashboardDataResult;

    /**
     * Every metric a section offers (what a dashboard may be built from).
     *
     * @return list<DashboardMetric>
     */
    public function metrics(DashboardSection $section): array;
}
