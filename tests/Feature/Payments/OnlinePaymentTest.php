<?php

use App\Dtos\Accounting\FiscalYearData;
use App\Dtos\Payments\GatewayChargeRequest;
use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserType;
use App\Interfaces\Accounting\FiscalPeriodServiceInterface;
use App\Interfaces\Payments\OnlinePaymentServiceInterface;
use App\Interfaces\Payments\PaymentGatewayInterface;
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
});

function onlinePost(string $url, array $payload = []): TestResponse
{
    return test()->postJson($url, $payload, ['Idempotency-Key' => (string) Str::uuid()]);
}

/** An issued invoice (15.000) of the patient's walk-in visit, billed at check-in by reception. */
function onlineInvoice(Patient $patient): string
{
    Sanctum::actingAs(User::factory()->withRole(RoleName::Receptionist->value)->create());
    $visit = onlinePost('/api/v1/appointments', [
        'patient_id' => $patient->id, 'provider_id' => FrontDesk::doctor()->id, 'type' => 'consultation',
        'scheduled_date' => '2026-11-01', 'start_time' => '08:30', 'source' => 'walk_in',
    ])->assertCreated()->json('data.id');
    test()->patchJson("/api/v1/appointments/{$visit}/check-in")->assertOk();
    $draft = test()->postJson('/api/v1/billing/invoices/charge', ['source_type' => 'appointment', 'source_id' => $visit])->assertOk()->json('data.id');

    return onlinePost("/api/v1/billing/invoices/{$draft}/issue")->assertOk()->json('data.id');
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
    $return = test()->get(substr($back, strpos($back, '/api/v1/')))->assertRedirect();

    return (string) $return->headers->get('Location');
}

it('lets a patient pay their invoice online; the money is booked only once the gateway says captured', function () {
    $patient = FrontDesk::patient();
    $invoice = onlineInvoice($patient);
    actAsPatient($patient);

    onlinePost("/api/v1/billing/invoices/{$invoice}/online-payments", ['amount' => '20.000'])
        ->assertUnprocessable()->assertJsonPath('error_code', 'ONLINE_PAYMENT_AMOUNT');
    $online = onlinePost("/api/v1/billing/invoices/{$invoice}/online-payments")->assertCreated()
        ->assertJsonPath('data.status', 'initiated')
        ->assertJsonPath('data.amount.amount', '15.000')
        ->json('data');
    expect($online['code'])->toStartWith('OPY-')->and($online['checkout_url'])->toContain('/payments/fake-checkout/');
    // Nothing is booked yet.
    $this->getJson("/api/v1/billing/invoices/{$invoice}")->assertJsonPath('data.balance_due.amount', '15.000');

    $landing = payOnFakeCheckout($online['checkout_url'], true);
    expect($landing)->toContain('/portal/invoices?online='.$online['id'].'&status=captured');

    actAsPatient($patient);
    $this->getJson("/api/v1/billing/online-payments/{$online['id']}")->assertOk()->assertJsonPath('data.status', 'captured')->assertJsonPath('data.checkout_url', null);
    $this->getJson("/api/v1/billing/invoices/{$invoice}")->assertJsonPath('data.status', 'paid')->assertJsonPath('data.payments.0.method', 'online');
});

it('books nothing for a declined payment and refuses unsigned webhooks', function () {
    $patient = FrontDesk::patient();
    $invoice = onlineInvoice($patient);
    actAsPatient($patient);
    $online = onlinePost("/api/v1/billing/invoices/{$invoice}/online-payments")->json('data');

    expect(payOnFakeCheckout($online['checkout_url'], false))->toContain('status=failed');
    $this->postJson('/api/v1/payments/webhooks/tap', ['id' => 'chg_x', 'status' => 'CAPTURED'], ['hashstring' => 'forged'])->assertUnauthorized();

    actAsPatient($patient);
    $this->getJson("/api/v1/billing/invoices/{$invoice}")->assertJsonPath('data.balance_due.amount', '15.000');
});

it('takes a signed webhook as a cue to look again, and puts a surplus on the balance when the desk was faster', function () {
    $patient = FrontDesk::patient();
    $invoice = onlineInvoice($patient);
    actAsPatient($patient);
    $online = onlinePost("/api/v1/billing/invoices/{$invoice}/online-payments")->json('data');
    $charge = OnlinePayment::query()->findOrFail($online['id'])->charge_id;

    // Meanwhile the patient pays 5.000 at the desk; then the online 15.000 is captured.
    Sanctum::actingAs(User::factory()->withRole(RoleName::Cashier->value)->create());
    onlinePost('/api/v1/billing/payments', ['invoice_id' => $invoice, 'amount' => '5.000', 'method' => 'card'])->assertCreated();
    FakeGateway::decide($charge, true);
    $signature = hash_hmac('sha256', $charge.'CAPTURED', FakeGateway::SECRET);
    $this->postJson('/api/v1/payments/webhooks/tap', ['id' => $charge, 'status' => 'CAPTURED'], ['hashstring' => $signature])->assertOk();

    $this->getJson("/api/v1/billing/invoices/{$invoice}")->assertJsonPath('data.status', 'paid');
    $this->getJson("/api/v1/billing/patients/{$patient->id}/wallet")->assertJsonPath('meta.balance.amount', '5.000');
});

it('gives up on attempts left on the payment page and keeps patients to their own invoices', function () {
    $patient = FrontDesk::patient();
    $invoice = onlineInvoice($patient);
    actAsPatient(FrontDesk::patient());
    onlinePost("/api/v1/billing/invoices/{$invoice}/online-payments")->assertNotFound();

    actAsPatient($patient);
    onlinePost("/api/v1/billing/invoices/{$invoice}/online-payments")->assertCreated();
    $this->travel(31)->minutes();
    expect(app(OnlinePaymentServiceInterface::class)->expireStale())->toBe(1);
});

it('asks Tap for the charge with the secret key and checks its webhook signature', function () {
    Config::set('services.tap.secret_key', 'sk_test_demo');
    Http::fake([
        'api.tap.company/v2/charges' => Http::response(['id' => 'chg_TS1', 'status' => 'INITIATED', 'amount' => 15, 'currency' => 'BHD', 'transaction' => ['url' => 'https://checkout.tap.company/x'], 'reference' => ['transaction' => 'OPY-1']]),
        'api.tap.company/v2/charges/chg_TS1' => Http::response(['id' => 'chg_TS1', 'status' => 'CAPTURED', 'amount' => 15, 'currency' => 'BHD']),
    ]);
    $tap = new TapGateway;

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
        ->and(app(PaymentGatewayInterface::class))->toBeInstanceOf(FakeGateway::class);
});
