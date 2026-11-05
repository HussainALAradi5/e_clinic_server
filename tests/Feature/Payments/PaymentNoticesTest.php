<?php

use App\Dtos\Accounting\FiscalYearData;
use App\Enums\Identity\RoleName;
use App\Enums\Reference\CompanyType;
use App\Interfaces\Accounting\FiscalPeriodServiceInterface;
use App\Interfaces\Payments\PaymentReminderServiceInterface;
use App\Models\Appointments\Patient;
use App\Models\Identity\User;
use App\Models\Payments\BillableService;
use App\Models\Reference\Company;
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

function noticePost(string $url, array $payload = []): TestResponse
{
    return test()->postJson($url, $payload, ['Idempotency-Key' => (string) Str::uuid()]);
}

/** An issued invoice (15.000) of the patient's walk-in visit; reception stays signed in. */
function noticeInvoice(Patient $patient): string
{
    Sanctum::actingAs(User::factory()->withRole(RoleName::Receptionist->value)->create());
    $visit = noticePost('/appointments', [
        'patient_id' => $patient->id, 'provider_id' => FrontDesk::doctor()->id, 'type' => 'consultation',
        'scheduled_date' => '2026-11-01', 'start_time' => '08:30', 'source' => 'walk_in',
    ])->assertCreated()->json('data.id');
    test()->patchJson("/appointments/{$visit}/check-in")->assertOk();
    $draft = test()->postJson('/billing/invoices/charge', ['source_type' => 'appointment', 'source_id' => $visit])->assertOk()->json('data.id');

    return noticePost("/billing/invoices/{$draft}/issue")->assertOk()->json('data.id');
}

function notices(string $template, string $personId): int
{
    return DB::table('notifications')->where('template', $template)->where('person_id', $personId)->count();
}

it('sends the patient a receipt notice for each payment they make', function () {
    $patient = FrontDesk::patient();
    $invoice = noticeInvoice($patient);
    Sanctum::actingAs(User::factory()->withRole(RoleName::Cashier->value)->create());
    noticePost('/billing/payments', ['invoice_id' => $invoice, 'amount' => '5.000', 'method' => 'card'])->assertCreated();

    expect(notices('payment.received', $patient->id))->toBeGreaterThan(0);
    $body = DB::table('notifications')->where('template', 'payment.received')->where('person_id', $patient->id)->value('body');
    expect($body)->toContain('5.000')->toContain('10.000');
});

it('reminds patients of their own part on schedule: first after 3 days, then weekly, at most 3 times', function () {
    $patient = FrontDesk::patient();
    $invoice = noticeInvoice($patient);
    $reminders = app(PaymentReminderServiceInterface::class);

    expect($reminders->sendDue())->toBe(0);
    $this->travel(3)->days();
    expect($reminders->sendDue())->toBe(1)->and($reminders->sendDue())->toBe(0);
    $this->travel(7)->days();
    expect($reminders->sendDue())->toBe(1);
    $this->travel(7)->days();
    expect($reminders->sendDue())->toBe(1);
    $this->travel(7)->days();
    expect($reminders->sendDue())->toBe(0);

    expect(notices('payment.reminder', $patient->id))->toBeGreaterThanOrEqual(3);
    Sanctum::actingAs(User::factory()->withRole(RoleName::Receptionist->value)->create());
    $this->getJson("/billing/invoices/{$invoice}")->assertJsonPath('data.reminders_sent', 3);
});

it('never reminds a patient of the insurer share, and lets staff send one by hand at most daily', function () {
    $insurer = Company::query()->create(['type' => CompanyType::Insurer, 'name' => 'Gulf Insurance Group', 'country_code' => 'BH']);
    $patient = FrontDesk::patient();
    $patient->forceFill(['insurance_company_id' => $insurer->id, 'insurance_policy_number' => 'GIG-1', 'insurance_expiry_date' => '2027-06-30', 'insurance_coverage_percent' => '80'])->save();
    BillableService::query()->update(['is_insurable' => true]);
    $invoice = noticeInvoice($patient);

    $this->postJson("/billing/invoices/{$invoice}/remind")->assertOk()->assertJsonPath('data.reminders_sent', 1);
    $this->postJson("/billing/invoices/{$invoice}/remind")->assertStatus(409)->assertJsonPath('error_code', 'REMINDER_NOT_ALLOWED');
    expect(DB::table('notifications')->where('template', 'payment.reminder')->value('body'))->toContain('3.000');

    // The co-pay is paid: only the insurer's share is left, so nobody reminds the patient.
    noticePost('/billing/payments', ['invoice_id' => $invoice, 'amount' => '3.000', 'method' => 'card'])->assertCreated();
    $this->travel(2)->days();
    $this->postJson("/billing/invoices/{$invoice}/remind")->assertStatus(409);
    $this->travel(10)->days();
    expect(app(PaymentReminderServiceInterface::class)->sendDue())->toBe(0);
});
