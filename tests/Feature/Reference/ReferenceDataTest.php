<?php

use App\Models\Identity\Person;
use App\Models\Reference\Company;
use App\Models\Reference\Country;
use Database\Seeders\CountrySeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(fn () => $this->seed(CountrySeeder::class));

const COMPANY_RIGHTS = ['companies.read_all', 'companies.create', 'companies.write', 'companies.delete'];

function bahrainAddress(array $overrides = []): array
{
    return ['country_code' => 'BH', 'flat' => '12', 'building' => '2504', 'road' => '2832', 'block' => '428', 'area' => 'Seef', ...$overrides];
}

it('seeds ISO countries with GCC first and serves the picker list publicly', function () {
    expect(Country::query()->count())->toBeGreaterThan(240);

    $this->getJson('/public/countries')
        ->assertOk()
        ->assertJsonPath('data.0.code', 'BH')
        ->assertJsonPath('data.0.name_ar', 'البحرين')
        ->assertJsonPath('data.0.nationality', 'Bahraini')
        ->assertJsonPath('data.0.phone_code', '+973')
        ->assertJsonPath('data.1.code', 'SA');
});

it('lets reference editors relabel or deactivate a country (hidden from pickers, cache flushed)', function () {
    actingWith('reference.read_all');
    $this->getJson('/reference/countries?search=bahr')->assertOk()->assertJsonPath('data.0.code', 'BH');
    $this->putJson('/reference/countries/AQ', ['is_active' => false])->assertForbidden();

    actingWith('reference.read_all', 'reference.write');
    $this->getJson('/reference/countries/options')->assertJsonFragment(['code' => 'AQ']);
    $this->putJson('/reference/countries/aq', ['is_active' => false, 'sort_order' => 900])->assertOk()->assertJsonPath('data.is_active', false);
    $this->getJson('/reference/countries/options')->assertJsonMissing(['code' => 'AQ']);
    $this->getJson('/reference/countries?include_inactive=1&filter[code][eq]=AQ')->assertJsonPath('meta.total', 1);
});

it('creates a company with a Bahrain address and prints it in English and Arabic', function () {
    actingWith(...COMPANY_RIGHTS);

    $id = $this->postJson('/reference/companies', [
        'type' => 'insurer', 'name' => 'Gulf Insurance', 'name_ar' => 'الخليج للتأمين', 'country_code' => 'bh',
        'email' => 'claims@gulf.example', 'address' => bahrainAddress(),
    ])->assertCreated()
        ->assertJsonPath('data.code', fn (string $code) => str_starts_with($code, 'CMP-'))
        ->assertJsonPath('data.country.name', 'Bahrain')
        ->assertJsonPath('data.address.formatted', 'Flat 12, Building 2504, Road 2832, Block 428, Seef, Bahrain')
        ->json('data.id');

    $addressId = Company::query()->findOrFail($id)->address_id;
    $this->getJson("/reference/addresses/{$addressId}/formatted?locale=ar")
        ->assertOk()->assertJsonPath('data.text', 'شقة 12، مبنى 2504، طريق 2832، مجمع 428، Seef، البحرين');

    $this->getJson("/reference/companies/{$id}")->assertOk()->assertJsonPath('data.usage', ['insured_patients' => 0]);
});

it('rejects an incomplete Bahrain address and an unknown country', function () {
    actingWith(...COMPANY_RIGHTS);

    $this->postJson('/reference/companies', ['type' => 'supplier', 'name' => 'X', 'country_code' => 'BH', 'address' => bahrainAddress(['block' => null])])
        ->assertUnprocessable()->assertJsonPath('error_code', 'ADDRESS_INVALID')->assertJsonStructure(['errors' => ['block']]);
    $this->postJson('/reference/companies', ['type' => 'supplier', 'name' => 'X', 'country_code' => 'ZZ'])
        ->assertUnprocessable()->assertJsonStructure(['errors' => ['country_code']]);
    $this->postJson('/reference/companies', ['type' => 'supplier', 'name' => 'Pharma GmbH', 'country_code' => 'DE', 'address' => ['country_code' => 'DE', 'line1' => 'Hauptstr. 1', 'city' => 'Berlin', 'postal_code' => '10115']])
        ->assertCreated()->assertJsonPath('data.address.formatted', 'Hauptstr. 1, Berlin 10115, Germany');
});

it('updates and removes a company address, and blocks deleting a company in use', function () {
    actingWith(...COMPANY_RIGHTS);
    $id = $this->postJson('/reference/companies', ['type' => 'manufacturer', 'name' => 'MedCo', 'country_code' => 'BH', 'address' => bahrainAddress()])->json('data.id');

    $this->putJson("/reference/companies/{$id}", ['address' => bahrainAddress(['flat' => null, 'area' => 'Juffair'])])
        ->assertOk()->assertJsonPath('data.address.area', 'Juffair');
    $this->putJson("/reference/companies/{$id}", ['address' => null])->assertOk()->assertJsonPath('data.address', null);
    $this->assertDatabaseCount('addresses', 0);

    Schema::create('medications_test', fn (Blueprint $t) => $t->uuid('manufacturer_id'));
    config(['reference.company_usages' => ['medications' => ['table' => 'medications_test', 'column' => 'manufacturer_id']]]);
    DB::table('medications_test')->insert(['manufacturer_id' => $id]);

    $this->deleteJson("/reference/companies/{$id}")->assertStatus(409)->assertJsonPath('error_code', 'COMPANY_IN_USE');
    $this->getJson("/reference/companies/{$id}")->assertJsonPath('data.usage.medications', 1);
    $this->postJson('/reference/companies/bulk', ['action' => 'archive', 'ids' => [$id]])->assertOk()->assertJsonPath('data.succeeded', 1);
    $this->getJson('/reference/companies')->assertJsonCount(0, 'data');
    $this->getJson('/reference/companies?include_archived=1&filter[type][eq]=manufacturer')->assertJsonCount(1, 'data');
});

it('saves a person\'s nationality and address and hides the address from users who cannot read the person', function () {
    actingWith('people.read_all', 'people.write', 'users.read_all');
    $person = Person::factory()->create();

    $this->putJson("/people/{$person->id}", ['version' => 1, 'nationality_code' => 'XX'])->assertUnprocessable();
    $this->putJson("/people/{$person->id}", ['version' => 1, 'nationality_code' => 'BH', 'address' => bahrainAddress()])
        ->assertOk()
        ->assertJsonPath('data.nationality.nationality', 'Bahraini')
        ->assertJsonPath('data.address.block', '428');

    $addressId = $person->refresh()->address_id;
    $this->getJson("/reference/addresses/{$addressId}")->assertOk()->assertJsonPath('data.road', '2832');

    actingWith('companies.read_all');
    $this->getJson("/reference/addresses/{$addressId}")->assertNotFound();
});
