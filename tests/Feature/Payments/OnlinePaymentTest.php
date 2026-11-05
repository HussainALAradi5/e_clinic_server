<?php

use App\Dtos\Accounting\FiscalYearData;
use App\Dtos\Payments\GatewayChargeRequest;
use App\Dtos\Reference\OnlinePaymentSettingsData;
use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserType;
use App\Interfaces\Accounting\FiscalPeriodServiceInterface;
use App\Interfaces\Payments\OnlinePaymentServiceInterface;
use App\Interfaces\Payments\PaymentGatewayInterface;
use App\Interfaces\Reference\ClinicProfileServiceInterface;
use App\Models\Appointments\Patient;
use App\Models\Identity\User;
use App\Models\Payments\OnlinePayment;
use App\Services\Payments\Gateways\FakeGateway;
use App\Services\Payments\Gateways\TapGateway;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
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
    // Off by default (admin settings); these tests are about paying, so the clinic has switched it on.
    app(ClinicProfileServiceInterface::class)->updateOnlinePayments(new OnlinePaymentSettingsData(true, null, null, null, false));
});

function onlinePost(string $url, array $payload = []): TestResponse
{
    return test()->postJson($url, $payload, ['Idempotency-Key' => (string) Str::uuid()]);
}

/** An issued invoice (15.000) of the patient's walk-in visit, billed at check-in by reception. */
function onlineInvoice(Patient $patient): string
{
    Sanctum::actingAs(User::factory()->withRole(RoleName::Receptionist->value)->create());
    $visit = onlinePost('/appointments', [
        'patient_id' => $patient->id, 'provider_id' => FrontDesk::doctor()->id, 'type' => 'consultation',
        'scheduled_date' => '2026-11-01', 'start_time' => '08:30', 'source' => 'walk_in',
    ])->assertCreated()->json('data.id');
    test()->patchJson("/appointments/{$visit}/check-in")->assertOk();
    $draft = test()->postJson('/billing/invoices/charge', ['source_type' => 'appointment', 'source_id' => $visit])->assertOk()->json('data.id');

    return onlinePost("/billing/invoices/{$draft}/issue")->assertOk()->json('data.id');
}

function actAsPatient(Patient $patient): void
{
    $user = User::query()->find($patient->id)
        ?? User::factory()->state(['id' => $patient->id, 'user_type' => UserType::Patient])->withRole(RoleName::Patient->value)->create();
    Sanctum::actingAs($user);
}

/** The test checkout's button, then the payer's way back; returns where they land. */
function payOnFakeCheckout(string $checkoutUrl, bool $pay): string
{
    $charge = basename((string) parse_url($checkoutUrl, PHP_URL_PATH));
    $back = (string) FakeGateway::decide($charge, $pay);
    $return = test()->get((string) parse_url($back, PHP_URL_PATH).'?'.parse_url($back, PHP_URL_QUERY))->assertRedirect();

    return (string) $return->headers->get('Location');
}

it('refuses online payment while the clinic has it switched off, and tells the apps', function () {
    $patient = FrontDesk::patient();
    $invoice = onlineInvoice($patient);
    app(ClinicProfileServiceInterface::class)->updateOnlinePayments(new OnlinePaymentSettingsData(false, null, null, null, false));
    $this->getJson('/public/clinic-profile')->assertOk()->assertJsonPath('data.online_payments_enabled', false);
    actAsPatient($patient);

    onlinePost("/billing/invoices/{$invoice}/online-payments")->assertStatus(409)->assertJsonPath('error_code', 'ONLINE_PAYMENTS_DISABLED');
    expect(OnlinePayment::query()->count())->toBe(0);
});

it('lets a patient pay their invoice online; the money is booked only once the gateway says captured', function () {
    $patient = FrontDesk::patient();
    $invoice = onlineInvoice($patient);
    actAsPatient($patient);

    onlinePost("/billing/invoices/{$invoice}/online-payments", ['amount' => '20.000'])
        ->assertUnprocessable()->assertJsonPath('error_code', 'ONLINE_PAYMENT_AMOUNT');
    $online = onlinePost("/billing/invoices/{$invoice}/online-payments")->assertCreated()
        ->assertJsonPath('data.status', 'initiated')
        ->assertJsonPath('data.amount.amount', '15.000')
        ->json('data');
    expect($online['code'])->toStartWith('OPY-')->and($online['checkout_url'])->toContain('/payments/fake-checkout/');
    // Nothing is booked yet.
    $this->getJson("/billing/invoices/{$invoice}")->assertJsonPath('data.balance_due.amount', '15.000');

    $landing = payOnFakeCheckout($online['checkout_url'], true);
    expect($landing)->toContain('/portal/invoices?online='.$online['id'].'&status=captured');

    actAsPatient($patient);
    $this->getJson("/billing/online-payments/{$online['id']}")->assertOk()->assertJsonPath('data.status', 'captured')->assertJsonPath('data.checkout_url', null);
    $this->getJson("/billing/invoices/{$invoice}")->assertJsonPath('data.status', 'paid')->assertJsonPath('data.payments.0.method', 'online');
});

it('books nothing for a declined payment and refuses unsigned webhooks', function () {
    $patient = FrontDesk::patient();
    $invoice = onlineInvoice($patient);
    actAsPatient($patient);
    $online = onlinePost("/billing/invoices/{$invoice}/online-payments")->json('data');

    expect(payOnFakeCheckout($online['checkout_url'], false))->toContain('status=failed');
    $this->postJson('/payments/webhooks/tap', ['id' => 'chg_x', 'status' => 'CAPTURED'], ['hashstring' => 'forged'])->assertUnauthorized();

    actAsPatient($patient);
    $this->getJson("/billing/invoices/{$invoice}")->assertJsonPath('data.balance_due.amount', '15.000');
});

it('takes a signed webhook as a cue to look again, and puts a surplus on the balance when the desk was faster', function () {
    $patient = FrontDesk::patient();
    $invoice = onlineInvoice($patient);
    actAsPatient($patient);
    $online = onlinePost("/billing/invoices/{$invoice}/online-payments")->json('data');
    $charge = OnlinePayment::query()->findOrFail($online['id'])->charge_id;

    // Meanwhile the patient pays 5.000 at the desk; then the online 15.000 is captured.
    Sanctum::actingAs(User::factory()->withRole(RoleName::Cashier->value)->create());
    onlinePost('/billing/payments', ['invoice_id' => $invoice, 'amount' => '5.000', 'method' => 'card'])->assertCreated();
    FakeGateway::decide($charge, true);
    $signature = hash_hmac('sha256', $charge.'CAPTURED', FakeGateway::SECRET);
    $this->postJson('/payments/webhooks/tap', ['id' => $charge, 'status' => 'CAPTURED'], ['hashstring' => $signature])->assertOk();

    $this->getJson("/billing/invoices/{$invoice}")->assertJsonPath('data.status', 'paid');
    $this->getJson("/billing/patients/{$patient->id}/wallet")->assertJsonPath('meta.balance.amount', '5.000');
});

it('gives up on attempts left on the payment page and keeps patients to their own invoices', function () {
    $patient = FrontDesk::patient();
    $invoice = onlineInvoice($patient);
    actAsPatient(FrontDesk::patient());
    onlinePost("/billing/invoices/{$invoice}/online-payments")->assertNotFound();

    actAsPatient($patient);
    onlinePost("/billing/invoices/{$invoice}/online-payments")->assertCreated();
    $this->travel(31)->minutes();
    expect(app(OnlinePaymentServiceInterface::class)->expireStale())->toBe(1);
});

it('asks Tap for the charge with the secret key and checks its webhook signature', function () {
    Config::set('services.tap.secret_key', 'sk_test_demo');
    Http::fake([
        'api.tap.company/v2/charges' => Http::response(['id' => 'chg_TS1', 'status' => 'INITIATED', 'amount' => 15, 'currency' => 'BHD', 'transaction' => ['url' => 'https://checkout.tap.company/x'], 'reference' => ['transaction' => 'OPY-1']]),
        'api.tap.company/v2/charges/chg_TS1' => Http::response(['id' => 'chg_TS1', 'status' => 'CAPTURED', 'amount' => 15, 'currency' => 'BHD']),
    ]);
    $tap = new TapGateway(app(ClinicProfileServiceInterface::class)->onlinePayments());

    $charge = $tap->createCharge(new GatewayChargeRequest('15.000', 'BHD', 'OPY-1', 'Invoice', 'Ali', 'Hasan', 'ali@example.com', '+97333000000', 'https://clinic/return', 'https://clinic/webhook'));
    expect($charge->checkoutUrl)->toBe('https://checkout.tap.company/x')->and($tap->retrieveCharge('chg_TS1')->amount)->toBe('15.000');
    Http::assertSent(fn (Request $r): bool => $r->url() === 'https://api.tap.company/v2/charges'
        && $r->hasHeader('Authorization', 'Bearer sk_test_demo')
        && $r['source']['id'] === 'src_all' && $r['currency'] === 'BHD'
        && $r['customer']['phone'] === ['country_code' => '973', 'number' => '33000000']);

    $payload = ['id' => 'chg_TS1', 'amount' => 15, 'currency' => 'BHD', 'reference' => ['gateway' => 'g1', 'payment' => 'p1'], 'status' => 'CAPTURED', 'transaction' => ['created' => '1730000000000']];
    $signed = hash_hmac('sha256', 'x_idchg_TS1x_amount15.000x_currencyBHDx_gateway_referenceg1x_payment_referencep1x_statusCAPTUREDx_created1730000000000', 'sk_test_demo');
    expect($tap->isValidWebhook($payload, $signed))->toBeTrue()
        ->and($tap->isValidWebhook([...$payload, 'amount' => 1500], $signed))->toBeFalse()
        // A secret key switches the clinic from the local test checkout to Tap.
        ->and(app(PaymentGatewayInterface::class))->toBeInstanceOf(TapGateway::class);
});
