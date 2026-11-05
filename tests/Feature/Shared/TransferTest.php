<?php

use App\Enums\Identity\UserType;
use App\Enums\Reference\CompanyType;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Models\Reference\Company;
use App\Utilities\Transfer\Xlsx\XlsxReader;
use App\Utilities\Transfer\Xlsx\XlsxWriter;
use Database\Seeders\CountrySeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(CountrySeeder::class);
});

/**
 * @param  list<list<string|null>>  $rows  first row = header
 */
function workbook(array $rows, string $sheet = 'Data'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    $xlsx = new XlsxWriter;
    $data = $xlsx->addSheet($sheet);
    foreach ($rows as $row) {
        $data->row($row);
    }
    $xlsx->save($path);

    return new UploadedFile($path, 'upload.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

/** @return list<array<int, string>> */
function readXlsx(TestResponse $response, ?string $sheet = null): array
{
    $path = tempnam(sys_get_temp_dir(), 'exp');
    file_put_contents($path, $response->streamedContent());

    return array_values(iterator_to_array((new XlsxReader)->rows($path, $sheet)));
}

function company(string $name, CompanyType $type = CompanyType::Supplier): Company
{
    return Company::query()->create(['name' => $name, 'type' => $type, 'country_code' => 'BH', 'email' => strtolower($name).'@example.org']);
}

it('exports the selected rows, or everything matching the filters, as Excel in the requested columns', function () {
    actingWith('companies.read_all', 'companies.export');
    $acme = company('Acme', CompanyType::Insurer);
    company('Beta');
    company('Gamma');

    $selected = $this->postJson('/reference/companies/export', ['format' => 'xlsx', 'ids' => [$acme->id], 'columns' => ['name', 'type']])
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect($selected->headers->get('content-disposition'))->toContain('companies-');
    // Branded sheet: title band, "Exported …" subtitle and a spacer above the header row.
    $rows = readXlsx($selected);
    expect($rows[0])->toBe(['Companies'])
        ->and($rows[1][0])->toStartWith('Exported ')
        ->and(array_slice($rows, 3))->toBe([['Name', 'Type'], ['Acme', 'Insurer']]);

    $filtered = $this->postJson('/reference/companies/export', ['format' => 'xlsx', 'filter' => ['search' => 'a', 'sort' => 'name'], 'columns' => ['name']]);
    expect(array_slice(readXlsx($filtered), 3))->toBe([['Name'], ['Acme'], ['Beta'], ['Gamma']]);
});

it('exports PDF and CSV, and refuses PDFs that are too long', function () {
    actingWith('companies.read_all', 'companies.export');
    company('Acme');
    company('=cmd');

    $pdf = $this->postJson('/reference/companies/export', ['format' => 'pdf'])->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($pdf->streamedContent())->toStartWith('%PDF-');

    $csv = $this->postJson('/reference/companies/export', ['format' => 'csv', 'columns' => ['name']])->assertOk();
    expect($csv->streamedContent())->toContain("\nAcme\n")->toContain("\n'=cmd\n"); // no formula injection

    config(['clinic.transfer.pdf_max_rows' => 1]);
    $this->postJson('/reference/companies/export', ['format' => 'pdf'])->assertStatus(422)->assertJsonPath('error_code', 'EXPORT_TOO_LARGE');
});

it('runs large exports as a background task', function () {
    actingWith('companies.read_all', 'companies.export');
    config(['clinic.transfer.export_sync_limit' => 1]);
    company('Acme');
    company('Beta');

    $this->postJson('/reference/companies/export', ['format' => 'xlsx'])
        ->assertStatus(202)
        ->assertJsonPath('data.rows', 2)
        ->assertJsonStructure(['data' => ['task_id']]);
});

it('needs the export / import right and never lets patients transfer data', function () {
    actingWith('companies.read_all');
    $this->getJson('/meta/resources/companies')->assertJsonPath('data.export_formats', [])->assertJsonPath('data.importable', false);
    $this->postJson('/reference/companies/export', ['format' => 'xlsx'])->assertForbidden()->assertJsonPath('error_code', 'TRANSFER_NOT_ALLOWED');
    $this->get('/reference/companies/import/template')->assertForbidden();

    actingWith('companies.read_all', 'companies.export', 'companies.import');
    $this->getJson('/meta/resources/companies')->assertJsonPath('data.export_formats', ['xlsx', 'pdf', 'csv'])->assertJsonPath('data.importable', true);

    $patient = grantPermissions(User::factory()->type(UserType::Patient)->create(), 'companies.read_all', 'companies.export', 'companies.import');
    Sanctum::actingAs($patient);
    $this->getJson('/meta/resources/companies')->assertJsonPath('data.export_formats', [])->assertJsonPath('data.importable', false);
    $this->postJson('/reference/companies/export', ['format' => 'csv'])->assertForbidden();
    $this->postJson('/reference/companies/import', ['file' => workbook([['Name *'], ['X']])])->assertForbidden();
});

it('offers an import template with the columns, an example row and instructions', function () {
    actingWith('companies.read_all', 'companies.import');

    $template = $this->get('/reference/companies/import/template')->assertOk();
    expect($template->headers->get('content-disposition'))->toContain('companies-import-template.xlsx');

    $data = readXlsx($template, 'Data');
    expect($data)->toHaveCount(1)
        ->and($data[0])->toContain('Name *', 'Type *', 'Country *', 'Email');
    $example = readXlsx($template, 'Example');
    expect($example[1][0])->toBe('Gulf Medical Supplies W.L.L.');
    expect(collect(readXlsx($template, 'Instructions'))->flatten()->all())->toContain('One of: Manufacturer, Supplier, Insurer, Laboratory, Hospital, Other');
});

it('imports every row in one request, accepting labels for choices', function () {
    actingWith('companies.read_all', 'companies.import');

    $this->post('/reference/companies/import', ['file' => workbook([
        ['Name *', 'Type *', 'Country *', 'Email', 'Address country', 'Building', 'Road', 'Block', 'City'],
        ['Gulf Medical', 'supplier', 'bh', 'orders@gulf.bh', 'BH', '2504', '2832', '428', 'Seef'],
        [],
        ['Seef Insurance', 'Insurer', 'BH', null, null, null, null, null, null],
    ])], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.created', 2)
        ->assertJsonPath('message', '2 records imported.');

    $gulf = Company::query()->where('name', 'Gulf Medical')->with('address')->firstOrFail();
    expect($gulf->type)->toBe(CompanyType::Supplier)
        ->and($gulf->country_code)->toBe('BH')
        ->and($gulf->address?->city)->toBe('Seef');
    expect(Company::query()->where('name', 'Seef Insurance')->value('type'))->toBe(CompanyType::Insurer);
});

it('imports nothing when a row is invalid and reports every problem with its row and column', function () {
    actingWith('companies.read_all', 'companies.import');

    $this->post('/reference/companies/import', ['file' => workbook([
        ['Name *', 'Type *', 'Country *', 'Email'],
        ['Fine Co', 'Supplier', 'BH', null],
        ['Bad Co', 'Spaceship', 'BH', 'not-an-email'],
    ])], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'IMPORT_FAILED')
        ->assertJsonPath('message', '1 row has problems; nothing was imported.')
        ->assertJsonPath('meta.rows.0.row', 3)
        ->assertJsonPath('meta.rows.0.column', 'Type')
        ->assertJsonPath('meta.rows.1.column', 'Email');

    expect(Company::query()->count())->toBe(0);

    $this->post('/reference/companies/import', ['file' => workbook([['Name *', 'Email'], ['X', null]])], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'IMPORT_FILE_INVALID')
        ->assertJsonPath('message', 'The file is missing required columns: Type, Country. Download the template to see them.');
});

it('imports people and refuses someone already registered', function () {
    actingWith('people.read_all', 'people.import');
    Person::factory()->create(['national_id' => '880421234']);

    $this->post('/people/import', ['file' => workbook([
        ['First name', 'Last name', 'Gender', 'Date of birth', 'Mobile', 'National ID (CPR)'],
        ['Fatima', 'Ali', 'Female', '32254', '+97336001234', null],
        ['Omar', 'Saleh', 'male', '1990-02-01', '+97336009999', '880421234'],
    ])], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonPath('meta.rows.0.row', 3)
        ->assertJsonPath('meta.rows.0.column', 'National ID (CPR)');
    expect(Person::query()->where('first_name', 'Fatima')->exists())->toBeFalse(); // all or nothing

    $this->post('/people/import', ['file' => workbook([
        ['First name', 'Last name', 'Gender', 'Date of birth', 'Mobile'],
        ['Fatima', 'Ali', 'Female', '32254', '+97336001234'],
    ])], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.created', 1);

    expect(Person::query()->where('first_name', 'Fatima')->value('date_of_birth')?->toDateString())->toBe('1988-04-21');
});
