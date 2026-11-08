<?php

declare(strict_types=1);

namespace App\Services\Dashboards;

use App\Dtos\Dashboards\DashboardData;
use App\Dtos\Dashboards\DashboardDataResult;
use App\Dtos\Dashboards\DashboardFilterDto;
use App\Dtos\Dashboards\DashboardMetric;
use App\Dtos\Dashboards\DashboardWidgetResult;
use App\Enums\Dashboards\DashboardMetricKind;
use App\Enums\Dashboards\DashboardSection;
use App\Exceptions\Dashboards\DashboardDefaultConflictException;
use App\Exceptions\Dashboards\DashboardIsDefaultException;
use App\Interfaces\Dashboards\DashboardServiceInterface;
use App\Models\Dashboards\Dashboard;
use App\Services\Base\BaseEntityService;
use App\Utilities\Access\PermissionExpression;
use App\Utilities\Query\ApplyListQuery;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseEntityService<Dashboard>
 */
final class DashboardService extends BaseEntityService implements DashboardServiceInterface
{
    public function __construct(ApplyListQuery $listQuery, private readonly DashboardMetricRegistry $registry)
    {
        parent::__construct($listQuery);
    }

    public function findDashboard(string $id): Dashboard
    {
        /** @var Dashboard $dashboard */
        $dashboard = $this->find($id);

        return $dashboard;
    }

    public function createDashboard(DashboardData $data): Dashboard
    {
        return $this->changingDefault(function () use ($data): Dashboard {
            $dashboard = new Dashboard($data->values);
            if ($dashboard->is_default) {
                $this->clearDefault($dashboard->section);
            }
            $dashboard->save();

            return $dashboard;
        });
    }

    public function updateDashboard(Dashboard $dashboard, DashboardData $data): Dashboard
    {
        $dashboard->fill($data->values)->save();

        return $dashboard;
    }

    public function deleteDashboard(Dashboard $dashboard): void
    {
        $this->delete($dashboard);
    }

    public function setDefault(Dashboard $dashboard): Dashboard
    {
        return $this->changingDefault(function () use ($dashboard): Dashboard {
            $this->clearDefault($dashboard->section, $dashboard->id);
            $dashboard->fill(['is_default' => true])->save();

            return $dashboard;
        });
    }

    public function data(Dashboard $dashboard, DashboardFilterDto $filter): DashboardDataResult
    {
        $user = Auth::user();
        $widgets = [];
        foreach (collect($dashboard->widgets)->sortBy('position') as $widget) {
            $metric = $this->registry->find($dashboard->section, $widget['metric']);
            if ($metric !== null && PermissionExpression::parse($metric->access)->evaluate($user)) {
                $widgets[] = $this->widgetResult($widget, $metric, $filter);
            }
        }

        return new DashboardDataResult($dashboard->id, $filter->date, $widgets);
    }

    public function metrics(DashboardSection $section): array
    {
        return $this->registry->metrics($section);
    }

    /**
     * @return Builder<Dashboard>
     */
    protected function query(): Builder
    {
        return Dashboard::query();
    }

    protected function beforeDelete(Model $model): void
    {
        /** @var Dashboard $model */
        if ($model->is_default) {
            throw new DashboardIsDefaultException;
        }
    }

    /**
     * @param  array{metric: string, title: ?string, size: string, position: int}  $widget
     */
    private function widgetResult(array $widget, DashboardMetric $metric, DashboardFilterDto $filter): DashboardWidgetResult
    {
        $result = $metric->resolve($filter);
        $isStat = $metric->kind === DashboardMetricKind::Stat;

        return new DashboardWidgetResult(
            metric: $metric->key,
            title: $widget['title'] ?? $metric->label,
            kind: $metric->kind,
            size: $widget['size'],
            position: $widget['position'],
            value: $isStat && is_int($result) ? $result : null,
            items: ! $isStat && is_array($result) ? $result : null,
        );
    }

    /** Takes the default away from the section's current one (through the model, so the change is audited). */
    private function clearDefault(DashboardSection $section, ?string $exceptId = null): void
    {
        Dashboard::query()->where('section', $section->value)->where('is_default', true)
            ->when($exceptId !== null, fn (Builder $q) => $q->whereKeyNot($exceptId))
            ->lockForUpdate()->get()
            ->each(fn (Dashboard $current) => $current->fill(['is_default' => false])->save());
    }

    /**
     * One transaction; a lost race on the one-default index comes back as a named conflict.
     *
     * @param  Closure(): Dashboard  $change
     */
    private function changingDefault(Closure $change): Dashboard
    {
        try {
            return DB::transaction($change);
        } catch (UniqueConstraintViolationException) {
            throw new DashboardDefaultConflictException;
        }
    }
}
