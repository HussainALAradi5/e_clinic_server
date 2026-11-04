<?php

use App\Dtos\Accounting\FiscalYearData;
use App\Enums\Identity\RoleName;
use App\Interfaces\Accounting\FiscalPeriodServiceInterface;
use App\Listeners\Accounting\SendMonthSummary;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FrontDesk;

beforeEach(function () {
    $this->seed([CountrySeeder::class, RolesAndPermissionsSeeder::class, ChartOfAccountsSeeder::class]);
    Notification::fake();
    // Sunday 1 Nov 2026, 08:00 in Bahrain.
    $this->travelTo(CarbonImmutable::parse('2026-11-01 05:00:00', 'UTC'));
    app(FiscalPeriodServiceInterface::class)->createYear(FiscalYearData::calendar(2026));
});

function sumPost(string $url, array $payload = []): TestResponse
{
    return test()->postJson($url, $payload, ['Idempotency-Key' => (string) Str::uuid()]);
}

function sumAs(RoleName $role): User
{
    $user = User::factory()->withRole($role->value)->withTwoFactor()->create();
    Sanctum::actingAs($user);

    return $user;
}

it('sums up the month next to the one before, for report readers only', function () {
    $doctor = FrontDesk::doctor();
    $patient = FrontDesk::patient();
    sumAs(RoleName::Receptionist);
    $visit = sumPost('/api/v1/appointments', [
        'patient_id' => $patient->id, 'provider_id' => $doctor->id, 'type' => 'consultation',
        'scheduled_date' => '2026-11-01', 'start_time' => '08:30', 'source' => 'walk_in',
    ])->json('data.id');
    $this->patchJson("/api/v1/appointments/{$visit}/check-in")->assertOk();
    Sanctum::actingAs(User::query()->findOrFail($doctor->id));
    $this->patchJson("/api/v1/appointments/{$visit}/start")->assertOk();
    $this->patchJson("/api/v1/appointments/{$visit}/complete", [])->assertOk();
    sumAs(RoleName::Receptionist);
    $draft = $this->getJson("/api/v1/billing/invoices?filter[patient_id][eq]={$patient->id}")->json('data.0.id');
    sumPost("/api/v1/billing/invoices/{$draft}/issue")->assertOk();
    sumAs(RoleName::Cashier);
    sumPost('/api/v1/billing/payments', ['invoice_id' => $draft, 'amount' => '10.000', 'method' => 'card'])->assertCreated();

    $this->travelTo(CarbonImmutable::parse('2026-12-01 05:00:00', 'UTC'));
    sumAs(RoleName::Receptionist);
    $this->getJson('/api/v1/accounting/month-summary?year=2026&month=11')->assertForbidden();

    sumAs(RoleName::Accountant);
    $summary = $this->getJson('/api/v1/accounting/month-summary?year=2026&month=11')->assertOk()->json('data');
    expect($summary['period'])->toBe(['from' => '2026-11-01', 'to' => '2026-11-30'])
        ->and($summary['current']['revenue']['amount'])->toBe('15.000')
        ->and($summary['current']['net_result']['amount'])->toBe('15.000')
        ->and($summary['current']['collected']['amount'])->toBe('10.000')
        ->and($summary['current']['owed_by_patients']['amount'])->toBe('5.000')
        ->and($summary['current']['visits_completed'])->toBe(1)
        ->and($summary['previous']['revenue']['amount'])->toBe('0.000')
        ->and($summary['top_doctors'][0]['doctor_id'])->toBe($doctor->id)
        ->and($summary['top_doctors'][0]['revenue']['amount'])->toBe('15.000');

    $sent = app(SendMonthSummary::class)();
    expect($sent)->toBeGreaterThan(0)
        ->and(DB::table('notifications')->where('template', 'report.month_summary')->value('body'))->toContain('15.000');
});
