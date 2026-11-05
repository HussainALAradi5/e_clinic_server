<?php

use App\Enums\Identity\FileScanStatus;
use App\Models\Identity\Attachment;
use App\Models\Identity\Person;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

const ATTACH_RIGHTS = ['attachments.read_all', 'attachments.create', 'attachments.write', 'attachments.delete', 'people.read_all', 'people.write'];

function pdf(string $name = 'report.pdf', string $body = 'hello'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n{$body}\n%%EOF");
}

function uploadTo(Person $person, array $files, string $category = 'insurance_document')
{
    return test()->post('/attachments', [
        'files' => $files,
        'attachable_type' => 'person',
        'attachable_id' => $person->id,
        'category' => $category,
    ], ['Accept' => 'application/json']);
}

it('saves valid files and reports invalid ones (207 partial success)', function () {
    actingWith(...ATTACH_RIGHTS);
    $person = Person::factory()->create();

    $response = uploadTo($person, [pdf(), UploadedFile::fake()->createWithContent('notes.exe', 'MZ binary')])
        ->assertStatus(207)
        ->assertJsonCount(1, 'data.saved')
        ->assertJsonPath('data.saved.0.original_name', 'report.pdf')
        ->assertJsonPath('data.saved.0.mime_type', 'application/pdf')
        ->assertJsonPath('data.saved.0.attachable_type', 'person')
        ->assertJsonPath('data.failed.0.index', 1)
        ->assertJsonPath('data.failed.0.code', 'ATTACHMENT_TYPE_NOT_ALLOWED');

    $attachment = Attachment::query()->findOrFail($response->json('data.saved.0.id'));
    expect($attachment->code)->toStartWith('ATT')
        ->and($attachment->scan_status)->toBe(FileScanStatus::Clean)
        ->and(Storage::disk('local')->exists($attachment->path))->toBeTrue();
});

it('returns 201 when all files are saved and flags duplicates', function () {
    actingWith(...ATTACH_RIGHTS);
    $person = Person::factory()->create();

    uploadTo($person, [pdf('a.pdf', 'one'), pdf('b.pdf', 'two')])->assertCreated()->assertJsonCount(2, 'data.saved');
    uploadTo($person, [pdf('a-copy.pdf', 'one')])->assertCreated()->assertJsonPath('data.saved.0.duplicate', true);

    expect(Attachment::query()->count())->toBe(2);
});

it('returns 422 when no file is accepted and 413 over the request limits', function () {
    actingWith(...ATTACH_RIGHTS);
    $person = Person::factory()->create();

    uploadTo($person, [UploadedFile::fake()->createWithContent('x.txt', 'plain')], 'payslip')->assertStatus(422);
    uploadTo($person, array_fill(0, 21, pdf()))->assertStatus(413)->assertJsonPath('error_code', 'UPLOAD_LIMIT_EXCEEDED');
});

it('needs write access to the owner record', function () {
    actingWith('attachments.read_all', 'attachments.create', 'people.read_all');

    uploadTo(Person::factory()->create(), [pdf()])->assertNotFound();
});

it('lists files of a record and gives a signed download link', function () {
    actingWith(...ATTACH_RIGHTS);
    $person = Person::factory()->create();
    $id = uploadTo($person, [pdf()])->json('data.saved.0.id');

    $this->getJson("/attachments?attachable_type=person&attachable_id={$person->id}")
        ->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonPath('meta.total', 1);

    $url = $this->getJson("/attachments/{$id}/download")->assertOk()->json('data.url');
    expect($this->get($url)->assertOk()->streamedContent())->toStartWith('%PDF');
});

it('gates sensitive files behind patients.view_medical and unscanned files behind the scan', function () {
    $user = actingWith(...ATTACH_RIGHTS);
    $person = Person::factory()->create();
    $id = uploadTo($person, [pdf()], 'medical_certificate')->json('data.saved.0.id');

    $this->getJson("/attachments/{$id}/download")->assertForbidden();

    grantPermissions($user, 'patients.view_medical');
    $this->getJson("/attachments/{$id}/download")->assertOk();

    Attachment::query()->whereKey($id)->update(['scan_status' => 'pending']);
    $this->getJson("/attachments/{$id}/download")->assertStatus(409)->assertJsonPath('error_code', 'ATTACHMENT_NOT_READY');
});

it('uploads large files in resumable chunks', function () {
    actingWith(...ATTACH_RIGHTS);
    $person = Person::factory()->create();
    $content = "%PDF-1.4\n".str_repeat('A', 3000)."\n%%EOF";

    $upload = $this->postJson('/attachments/uploads', [
        'file_name' => 'scan.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => strlen($content),
        'category' => 'lab_result', 'sha256' => hash('sha256', $content),
    ])->assertCreated()->assertJsonPath('data.received_bytes', 0)->json('data.id');

    $chunk = fn (string $body, int $offset) => $this->call('PATCH', "/attachments/uploads/{$upload}", [], [], [], [
        'HTTP_UPLOAD_OFFSET' => (string) $offset, 'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/offset+octet-stream',
    ], $body);

    $chunk(substr($content, 0, 1000), 0)->assertOk()->assertJsonPath('data.received_bytes', 1000)->assertJsonPath('data.status', 'in_progress');
    $chunk(substr($content, 1000), 0)->assertStatus(409)->assertJsonPath('error_code', 'UPLOAD_SESSION_INVALID');
    $this->getJson("/attachments/uploads/{$upload}")->assertJsonPath('data.received_bytes', 1000);
    $chunk(substr($content, 1000), 1000)->assertOk()->assertJsonPath('data.received_bytes', strlen($content));

    $this->postJson("/attachments/uploads/{$upload}/complete", ['attachable_type' => 'person', 'attachable_id' => $person->id])
        ->assertCreated()
        ->assertJsonPath('data.category', 'lab_result')
        ->assertJsonPath('data.size_bytes', strlen($content))
        ->assertJsonPath('data.sha256', hash('sha256', $content));
});

it('zips several files in a background task', function () {
    actingWith(...ATTACH_RIGHTS);
    $person = Person::factory()->create();
    $ids = collect(uploadTo($person, [pdf('a.pdf', '1'), pdf('b.pdf', '2')])->json('data.saved'))->pluck('id')->all();

    $task = $this->postJson('/attachments/zip', ['attachment_ids' => $ids])->assertStatus(202)->json('meta.task_id');

    $this->getJson("/tasks/{$task}")->assertJsonPath('data.status', 'completed')->assertJsonPath('data.result_summary.files', 2);
    $url = $this->getJson("/tasks/{$task}/download")->json('data.url');
    expect($this->get($url)->assertOk()->headers->get('content-disposition'))->toContain('.zip');
});

it('archives, deletes and restores attachments (also in bulk)', function () {
    actingWith(...ATTACH_RIGHTS);
    $id = uploadTo(Person::factory()->create(), [pdf()])->json('data.saved.0.id');

    $this->patchJson("/attachments/{$id}/archive")->assertOk()->assertJsonPath('data.is_archived', true);
    $this->postJson('/attachments/bulk', ['action' => 'unarchive', 'ids' => [$id]])->assertOk()->assertJsonPath('data.succeeded', 1);
    $this->deleteJson("/attachments/{$id}")->assertNoContent();
    $this->getJson("/attachments/{$id}")->assertNotFound();
    $this->patchJson("/attachments/{$id}/restore")->assertOk()->assertJsonPath('data.is_deleted', false);
});
