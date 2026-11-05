<?php

use App\Interfaces\Identity\CodeServiceInterface;
use App\Models\Identity\Attachment;
use App\Models\Identity\Person;

const CODE_RIGHTS = ['codes.read_all', 'codes.generate', 'codes.scan', 'people.read_all'];

it('renders a signed QR code (SVG or PNG) for a readable record', function () {
    actingWith(...CODE_RIGHTS);
    $person = Person::factory()->create();

    $svg = $this->get("/codes/person/{$person->id}")->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    expect($svg->getContent())->toContain('<svg')
        ->and($svg->headers->get('X-Code-Payload'))->toStartWith("person:{$person->code}.");

    $png = $this->get("/codes/person/{$person->id}?symbology=code128&format=png&size=300")->assertOk()->assertHeader('Content-Type', 'image/png');
    expect(substr((string) $png->getContent(), 1, 3))->toBe('PNG');

    $this->getJson("/codes/person/{$person->id}?size=10")->assertUnprocessable();
});

it('hides records the user cannot read and unknown entities', function () {
    actingWith('codes.read_all', 'codes.generate');

    $this->getJson('/codes/person/'.Person::factory()->create()->id)->assertNotFound();
    $this->getJson('/codes/invoice/'.Person::factory()->create()->id)->assertNotFound();
});

it('resolves scanned codes and rejects forged ones', function () {
    actingWith(...CODE_RIGHTS);
    $person = Person::factory()->create();
    $payload = app(CodeServiceInterface::class)->payloadFor('person', $person->code);

    $this->postJson('/codes/scan', ['payload' => $payload, 'purpose' => 'identify'])
        ->assertOk()
        ->assertJsonPath('data.entity', 'person')
        ->assertJsonPath('data.id', $person->id)
        ->assertJsonPath('data.summary.full_name', $person->full_name)
        ->assertJsonPath('data.summary.date_of_birth', $person->date_of_birth->format('Y-m-d'));

    $forged = substr($payload, 0, -1).(str_ends_with($payload, '0') ? '1' : '0');
    $this->postJson('/codes/scan', ['payload' => $forged, 'purpose' => 'identify'])
        ->assertUnprocessable()->assertJsonPath('error_code', 'SCAN_CODE_INVALID');
    $this->postJson('/codes/scan', ['payload' => 'garbage', 'purpose' => 'identify'])->assertUnprocessable();
});

it('verifies public documents without exposing private entities', function () {
    $person = Person::factory()->create();
    $codes = app(CodeServiceInterface::class);

    // Genuine but private: confirmed, nothing about the record is shown.
    $this->getJson('/public/verify/'.$codes->payloadFor('person', $person->code))
        ->assertOk()->assertJsonPath('data.valid', true)->assertJsonPath('data.hidden', true)
        ->assertJsonPath('data.entity', 'person')->assertJsonPath('data.id', '')->assertJsonPath('data.summary', [])
        ->assertJsonPath('data.document', null);
    $forged = substr($codes->payloadFor('person', $person->code), 0, -1).'x';
    $this->getJson('/public/verify/'.$forged)->assertUnprocessable();

    $attachment = Attachment::query()->create([
        'attachable_type' => 'person', 'attachable_id' => $person->id, 'category' => 'payslip', 'original_name' => 'p.pdf',
        'mime_type' => 'application/pdf', 'size_bytes' => 10, 'disk' => 'local', 'path' => 'x', 'sha256' => str_repeat('a', 64),
    ]);
    $this->getJson('/public/verify/'.$codes->payloadFor('attachment', $attachment->code))
        ->assertOk()
        ->assertJsonPath('data.valid', true)
        ->assertJsonPath('data.summary.category', 'payslip')
        ->assertJsonMissingPath('data.summary.original_name');
});
