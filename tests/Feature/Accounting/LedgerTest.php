<?php

use App\Enums\Identity\RoleName;
use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalPeriod;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\FiscalYearSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed([CountrySeeder::class, RolesAndPermissionsSeeder::class, ChartOfAccountsSeeder::class]);
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-01 05:00:00', 'UTC'));
    $this->seed(FiscalYearSeeder::class);
});

function ledgerUser(RoleName $role = RoleName::Accountant): User
{
    $user = User::factory()->withRole($role->value)->create();
    Sanctum::actingAs($user);

    return $user;
}

function account(string $code): string
{
    return Account::query()->where('code', $code)->valueOrFail('id');
}

/** @return array<string, mixed> */
function entry(string $date = '2026-11-01', string $amount = '100.000'): array
{
    return [
        'entry_date' => $date, 'type' => 'manual', 'description' => 'Owner capital paid into the bank',
        'lines' => [
            ['account_id' => account('1120'), 'debit' => $amount],
            ['account_id' => account('3100'), 'credit' => $amount],
        ],
    ];
}

it('runs a manual entry from draft to posted and reverses it; unbalanced and posted entries are refused', function () {
    ledgerUser();
    $unbalanced = entry();
    $unbalanced['lines'][1]['credit'] = '90.000';
    // A draft may be saved half-done; it only has to balance to post.
    $half = $this->postJson('/api/v1/accounting/journal-entries', $unbalanced)->assertCreated()->json('data');
    $this->postJson("/api/v1/accounting/journal-entries/{$half['id']}/post")->assertUnprocessable()->assertJsonPath('error_code', 'JOURNAL_UNBALANCED');
    $this->deleteJson("/api/v1/accounting/journal-entries/{$half['id']}")->assertNoContent();

    $draft = $this->postJson('/api/v1/accounting/journal-entries', entry())->assertCreated()
        ->assertJsonPath('data.status', 'draft')->assertJsonPath('data.total_debit.amount', '100.000')->json('data');
    $this->putJson("/api/v1/accounting/journal-entries/{$draft['id']}", entry(amount: '150.000'))->assertOk()->assertJsonPath('data.total_credit.amount', '150.000');

    $posted = $this->postJson("/api/v1/accounting/journal-entries/{$draft['id']}/post")->assertOk()->assertJsonPath('data.status', 'posted')->json('data');
    expect($posted['entry_number'])->not->toBeNull();
    $this->putJson("/api/v1/accounting/journal-entries/{$draft['id']}", entry())->assertStatus(409);

    $reversal = $this->postJson("/api/v1/accounting/journal-entries/{$draft['id']}/reverse", ['date' => '2026-11-01', 'reason' => 'Booked twice'])->assertOk()->json('data');
    $this->getJson("/api/v1/accounting/journal-entries/{$draft['id']}")->assertJsonPath('data.status', 'reversed')->assertJsonPath('data.reversed_by.id', $reversal['id']);
    $this->getJson('/api/v1/accounting/journal-entries?filter[status][eq]=posted')->assertOk()->assertJsonCount(1, 'data');
});

it('closes months in order so nothing more can post into them', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    ledgerUser();
    $years = $this->getJson('/api/v1/accounting/fiscal-years')->assertOk()->assertJsonCount(1, 'data')->json('data');
    expect($years[0]['name'])->toBe('FY2026')->and($years[0]['periods'])->toHaveCount(12);

    // October cannot close while September is open — close the year's months in order.
    $october = FiscalPeriod::query()->where('name', '2026-10')->firstOrFail();
    $this->postJson("/api/v1/accounting/fiscal-years/periods/{$october->id}/close")->assertStatus(409)->assertJsonPath('error_code', 'PREVIOUS_PERIOD_OPEN');
    foreach (FiscalPeriod::query()->where('name', '<=', '2026-10')->orderBy('period_number')->get() as $period) {
        $this->postJson("/api/v1/accounting/fiscal-years/periods/{$period->id}/close")->assertOk()->assertJsonPath('data.status', 'closed');
    }
    $this->postJson('/api/v1/accounting/journal-entries', entry('2026-10-15'))->assertCreated();
    $draft = $this->postJson('/api/v1/accounting/journal-entries', entry('2026-10-20'))->json('data');
    $this->postJson("/api/v1/accounting/journal-entries/{$draft['id']}/post")->assertStatus(409)->assertJsonPath('error_code', 'FISCAL_PERIOD_CLOSED');
});

it('builds every financial report from the posted ledger', function () {
    ledgerUser();
    $draft = $this->postJson('/api/v1/accounting/journal-entries', entry())->json('data');
    $this->postJson("/api/v1/accounting/journal-entries/{$draft['id']}/post")->assertOk();

    $trial = $this->getJson('/api/v1/accounting/reports/trial-balance?as_of=2026-11-30')->assertOk()
        ->assertJsonPath('data.type', 'trial_balance')->json('data');
    expect($trial['columns'])->not->toBeEmpty()->and($trial['rows'])->not->toBeEmpty();
    $this->getJson('/api/v1/accounting/reports/balance-sheet?as_of=2026-11-30')->assertOk();
    $this->getJson('/api/v1/accounting/reports/income-statement?from=2026-11-01&to=2026-11-30')->assertOk();
    $this->getJson('/api/v1/accounting/reports/cash-flow?from=2026-11-01&to=2026-11-30')->assertOk();
    $this->getJson('/api/v1/accounting/reports/general-ledger?from=2026-11-01&to=2026-11-30&account_id='.account('1120'))->assertOk()
        ->assertJsonPath('data.type', 'general_ledger');
    $this->getJson('/api/v1/accounting/reports/revenue-by-doctor?from=2026-11-01&to=2026-11-30')->assertOk();
    $this->getJson('/api/v1/accounting/reports/revenue-by-service?from=2026-11-01&to=2026-11-30')->assertOk();
    $this->getJson('/api/v1/accounting/reports/receivables-aging?as_of=2026-11-30')->assertOk();
    $this->getJson('/api/v1/accounting/reports/income-statement?from=2026-11-30&to=2026-11-01')->assertUnprocessable();
    foreach (['xlsx', 'pdf'] as $format) {
        $this->postJson('/api/v1/accounting/reports/export', ['type' => 'trial_balance', 'format' => $format, 'params' => ['as_of' => '2026-11-30']])
            ->assertOk()->assertDownload();
    }
});
