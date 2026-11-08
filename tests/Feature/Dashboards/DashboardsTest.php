<?php

use App\Enums\Dashboards\AttendanceMetric;
use App\Enums\Dashboards\DashboardSection;
use App\Enums\HR\AttendanceSource;
use App\Enums\HR\AttendanceStatus;
use App\Enums\HR\ShiftType;
use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserType;
use App\Enums\Shared\ApprovalStatus;
use App\Models\Dashboards\Dashboard;
use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use App\Models\HR\Shift;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CountrySeeder;
use Database\Seeders\DashboardSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FrontDesk;

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->seed([CountrySeeder::class, RolesAndPermissionsSeeder::class]);
    // Sunday 1 Nov 2026, 10:00 in Bahrain (the clinic week is Sunday–Thursday).
    $this->travelTo(CarbonImmutable::parse('2026-11-01 07:00:00', 'UTC'));
});

/** @return list<array{metric: string, title?: string, size: string, position: int}> */
function dashboardWidgets(): array
{
    return [
        ['metric' => AttendanceMetric::Present->value, 'size' => 'small', 'position' => 1],
        ['metric' => AttendanceMetric::PendingOvertime->value, 'title' => 'To approve', 'size' => 'small', 'position' => 0],
        ['metric' => AttendanceMetric::LateArrivals->value, 'size' => 'large', 'position' => 2],
    ];
}

/** @param array<string, mixed> $overrides */
function dashboardMake(array $overrides = []): Dashboard
{
    return Dashboard::query()->create(['name' => 'Attendance', 'section' => DashboardSection::Attendance, 'widgets' => dashboardWidgets(), ...$overrides]);
}

function dashboardActingAs(RoleName $role, UserType $type = UserType::MedicalStaff): void
{
    Sanctum::actingAs(User::query()->findOrFail(FrontDesk::employee($type, $role)->id));
}

/** @param array<string, mixed> $overrides */
function dashboardDay(Employee $employee, AttendanceStatus $status, array $overrides = []): Attendance
{
    return Attendance::query()->create(['employee_id' => $employee->id, 'date' => '2026-11-01', 'status' => $status, 'source' => AttendanceSource::Manual, ...$overrides]);
}

/** @return array<string, mixed> metric key => value or items */
function dashboardFigures(string $url): array
{
    return collect(test()->getJson($url)->assertOk()->json('data.widgets'))
        ->mapWithKeys(fn (array $w): array => [$w['metric'] => $w['kind'] === 'stat' ? $w['value'] : $w['items']])->all();
}

function dashboardAllMetrics(): Dashboard
{
    return dashboardMake(['widgets' => array_map(
        fn (AttendanceMetric $metric, int $i): array => ['metric' => $metric->value, 'title' => null, 'size' => 'small', 'position' => $i],
        AttendanceMetric::cases(),
        array_keys(AttendanceMetric::cases()),
    )]);
}

it('lets HR create, read, update and delete a dashboard', function () {
    dashboardActingAs(RoleName::HrManager, UserType::Admin);

    $created = $this->postJson('/dashboards', ['name' => 'Morning', 'section' => 'attendance', 'widgets' => dashboardWidgets()])
        ->assertCreated()->assertJsonPath('data.section', 'attendance')->assertJsonPath('data.is_default', false);
    $id = $created->json('data.id');
    expect($created->json('data.code'))->toStartWith('DSH-')
        ->and(array_column($created->json('data.widgets'), 'position'))->toBe([0, 1, 2]);

    $this->getJson("/dashboards/{$id}")->assertOk()->assertJsonPath('data.name', 'Morning');
    $this->putJson("/dashboards/{$id}", ['name' => 'Evening'])->assertOk()->assertJsonPath('data.name', 'Evening');
    $this->deleteJson("/dashboards/{$id}")->assertNoContent();

    $this->getJson("/dashboards/{$id}")->assertNotFound();
    expect(Dashboard::query()->withDeleted()->whereKey($id)->value('is_deleted'))->toBeTruthy();
});

it('keeps staff to reading and patients out', function () {
    $dashboard = dashboardMake();

    dashboardActingAs(RoleName::Receptionist, UserType::Receptionist);
    $this->getJson('/dashboards')->assertOk();
    $this->getJson("/dashboards/{$dashboard->id}/data")->assertOk();
    $this->postJson('/dashboards', ['name' => 'X', 'section' => 'attendance', 'widgets' => dashboardWidgets()])->assertForbidden();
    $this->patchJson("/dashboards/{$dashboard->id}/default")->assertForbidden();
    $this->deleteJson("/dashboards/{$dashboard->id}")->assertForbidden();

    Sanctum::actingAs(User::factory()->state(['user_type' => UserType::Patient])->withRole(RoleName::Patient->value)->create());
    $this->getJson('/dashboards')->assertForbidden();
});

it('sends only the widgets the user may see', function () {
    $dashboard = dashboardMake();
    $url = "/dashboards/{$dashboard->id}/data";

    actingWith('dashboards.read_all');
    $this->getJson($url)->assertOk()->assertJsonCount(0, 'data.widgets');

    actingWith('dashboards.read_all', 'attendance.read_all');
    expect(array_keys(dashboardFigures($url)))->toBe(['present', 'late_arrivals']);

    actingWith('dashboards.read_all', 'attendance.read_all', 'attendance.approve_overtime');
    $widgets = $this->getJson($url)->assertOk()->json('data.widgets');
    expect(array_column($widgets, 'metric'))->toBe(['pending_overtime', 'present', 'late_arrivals'])
        ->and($widgets[0]['title'])->toBe('To approve')
        ->and($widgets[1]['title'])->toBe('Present');
});

it('rejects a metric the section does not have', function () {
    actingWith('dashboards.write');
    $widgets = [['metric' => 'revenue', 'size' => 'small', 'position' => 0]];

    $this->postJson('/dashboards', ['name' => 'Bad', 'section' => 'attendance', 'widgets' => $widgets])
        ->assertUnprocessable()->assertJsonValidationErrors('widgets.0.metric');
    $this->putJson('/dashboards/'.dashboardMake()->id, ['widgets' => $widgets])
        ->assertUnprocessable()->assertJsonValidationErrors('widgets.0.metric');
});

it('rejects a changed section or default flag on update', function () {
    actingWith('dashboards.write');

    $this->putJson('/dashboards/'.dashboardMake()->id, ['section' => 'attendance', 'is_default' => true])
        ->assertUnprocessable()->assertJsonValidationErrors(['section', 'is_default']);
});

it('keeps one default per section', function () {
    actingWith('dashboards.write');
    $first = dashboardMake(['is_default' => true]);
    $second = dashboardMake();

    $this->patchJson("/dashboards/{$second->id}/default")->assertOk()->assertJsonPath('data.is_default', true);
    expect($first->fresh()->is_default)->toBeFalse();

    $third = $this->postJson('/dashboards', ['name' => 'New', 'section' => 'attendance', 'is_default' => true, 'widgets' => dashboardWidgets()])->assertCreated();
    expect($second->fresh()->is_default)->toBeFalse()
        ->and(Dashboard::query()->where('is_default', true)->pluck('id')->all())->toBe([$third->json('data.id')]);
});

it('enforces the single default in the database', function () {
    dashboardMake(['is_default' => true]);

    expect(fn () => dashboardMake(['is_default' => true]))->toThrow(UniqueConstraintViolationException::class);
});

it('does not delete the default of a section', function () {
    actingWith('dashboards.delete');

    $this->deleteJson('/dashboards/'.dashboardMake(['is_default' => true])->id)
        ->assertConflict()->assertJsonPath('error_code', 'DASHBOARD_IS_DEFAULT');
});

it('computes the figures of a known day', function () {
    actingWith('dashboards.read_all', 'attendance.read_all', 'attendance.approve_overtime');
    $employee = fn (): Employee => FrontDesk::employee(UserType::MedicalStaff, RoleName::Nurse);

    $late = $employee();
    dashboardDay($late, AttendanceStatus::Late, ['check_in_at' => '2026-11-01 05:20:00', 'late_minutes' => 20, 'overtime_minutes' => 30, 'overtime_approved' => true]);
    dashboardDay($employee(), AttendanceStatus::Present, ['overtime_minutes' => 45]);
    dashboardDay($employee(), AttendanceStatus::Present);
    dashboardDay($employee(), AttendanceStatus::HalfDay, ['needs_review' => true]);
    dashboardDay($employee(), AttendanceStatus::Absent);
    dashboardDay($employee(), AttendanceStatus::OnLeave);
    $employee(); // no row, start time passed: absent
    $onLeave = $employee(); // no row, approved leave: on leave
    $type = LeaveType::query()->create(['code' => 'ANNUAL', 'name' => 'Annual leave']);
    LeaveRequest::query()->create(['employee_id' => $onLeave->id, 'leave_type_id' => $type->id, 'start_date' => '2026-11-01', 'end_date' => '2026-11-02', 'total_days' => 2, 'status' => ApprovalStatus::Approved]);
    tap($employee())->update(['work_start_time' => '12:00']); // no row, not due yet: not counted
    tap($employee())->update(['weekly_off_days' => [0]]); // day off: not counted

    $figures = dashboardFigures('/dashboards/'.dashboardAllMetrics()->id.'/data');

    expect($figures)->toMatchArray([
        'present' => 4, 'late' => 1, 'absent' => 2, 'on_leave' => 2,
        'pending_overtime' => 1, 'approved_overtime_minutes' => 30, 'needs_review' => 1,
    ])->and($figures['late_arrivals'])->toHaveCount(1)
        ->and($figures['late_arrivals'][0]['title'])->toBe($late->person->full_name)
        ->and($figures['late_arrivals'][0]['value'])->toBe('20 min late');
});

it('reads an earlier day and refuses a future one', function () {
    actingWith('dashboards.read_all', 'attendance.read_all');
    $employee = FrontDesk::employee(UserType::MedicalStaff, RoleName::Nurse);
    Attendance::query()->create(['employee_id' => $employee->id, 'date' => '2026-10-29', 'status' => AttendanceStatus::Present, 'source' => AttendanceSource::Manual]);
    $url = '/dashboards/'.dashboardMake()->id.'/data';

    $this->getJson("{$url}?date=2026-10-29")->assertOk()->assertJsonPath('data.date', '2026-10-29')->assertJsonPath('data.widgets.0.value', 1);
    $this->getJson("{$url}?date=2026-11-02")->assertUnprocessable()->assertJsonValidationErrors('date');
});

it('does not query more as the late list grows', function () {
    actingWith('dashboards.read_all', 'attendance.read_all', 'attendance.approve_overtime');
    $url = '/dashboards/'.dashboardAllMetrics()->id.'/data';
    $addLate = fn () => dashboardDay(FrontDesk::employee(UserType::MedicalStaff, RoleName::Nurse), AttendanceStatus::Late, ['late_minutes' => 15]);
    $queries = function () use ($url): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson($url)->assertOk();

        return count(DB::getQueryLog());
    };

    $addLate();
    $queries(); // permission caches warm up
    $one = $queries();
    $addLate();
    $addLate();

    expect($queries())->toBe($one);
});

it('paginates and filters the list', function () {
    actingWith('dashboards.read_all');
    dashboardMake(['name' => 'Alpha']);
    dashboardMake(['name' => 'Beta', 'is_default' => true]);
    dashboardMake(['name' => 'Gamma']);

    $this->getJson('/dashboards?per_page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3)->assertJsonPath('meta.last_page', 2);
    // The query the web client sends for a section's default.
    $this->getJson('/dashboards?per_page=1&filter[and][0][section][eq]=attendance&filter[and][1][is_default][eq]=true')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Beta');
});

it('lists the metrics of a section', function () {
    actingWith('dashboards.read_all');

    $this->getJson('/dashboards/metrics?section=attendance')->assertOk()
        ->assertJsonCount(count(AttendanceMetric::cases()), 'data')
        ->assertJsonPath('data.0.key', 'present')->assertJsonPath('data.0.kind', 'stat');
    $this->getJson('/dashboards/metrics?section=nothing')->assertUnprocessable()->assertJsonValidationErrors('section');
});

it('seeds one default attendance dashboard and can run again', function () {
    $this->seed(DashboardSeeder::class);
    $this->seed(DashboardSeeder::class);

    $dashboards = Dashboard::query()->get();
    expect($dashboards)->toHaveCount(1)
        ->and($dashboards[0]->is_default)->toBeTrue()
        ->and(array_column($dashboards[0]->widgets, 'metric'))->toContain(...array_column(AttendanceMetric::cases(), 'value'));
});

it('uses the shift start, like lateness does, before counting someone absent', function () {
    actingWith('dashboards.read_all', 'attendance.read_all');
    $shift = Shift::query()->create(['name' => 'Afternoon', 'type' => ShiftType::Fixed, 'start_time' => '14:00', 'end_time' => '22:00', 'required_minutes' => 480]);
    FrontDesk::employee(UserType::MedicalStaff, RoleName::Nurse); // the default 08:00 start has passed: absent
    tap(FrontDesk::employee(UserType::MedicalStaff, RoleName::Nurse))->update(['shift_id' => $shift->id]); // starts at 14:00: not due yet

    expect(dashboardFigures('/dashboards/'.dashboardAllMetrics()->id.'/data')['absent'])->toBe(1);
});

it('answers 422, not a crash, when the section is not text', function () {
    actingWith('dashboards.read_all', 'dashboards.write');

    $this->postJson('/dashboards', ['name' => 'Attendance', 'section' => [], 'widgets' => dashboardWidgets()])->assertUnprocessable();
});
