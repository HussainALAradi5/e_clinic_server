<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Dtos\Documents\WrittenDocumentData;
use App\Enums\Appointments\AppointmentStatus;
use App\Enums\Documents\WrittenDocumentKind;
use App\Enums\Identity\UserType;
use App\Events\Documents\WrittenDocumentIssued;
use App\Exceptions\Documents\WrittenDocumentNotAllowedException;
use App\Exceptions\Shared\InvalidStateTransitionException;
use App\Interfaces\Documents\DocumentTemplateServiceInterface;
use App\Interfaces\Documents\WrittenDocumentServiceInterface;
use App\Interfaces\Reference\ClinicProfileServiceInterface;
use App\Models\Appointments\Appointment;
use App\Models\Documents\WrittenDocument;
use App\Models\HR\Employee;
use App\Models\Identity\User;
use App\Services\Base\BaseEntityService;
use App\Utilities\Access\OwnershipScope;
use App\Utilities\Documents\PlaceholderFiller;
use App\Utilities\Documents\RichText;
use App\Utilities\Query\ApplyListQuery;
use App\Utilities\Query\ListQuery;
use App\Utilities\Shared\ClinicClock;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Documents written from a visit session — medical reports, attendance and fitness certificates, referral notes,
 * letters. Only the visit's own provider writes them, once the patient has arrived: a doctor for every kind; the nurse
 * running a treatment session may also write an attendance certificate. A draft fills a template (or the author's own
 * text) from the visit ("auto generate"); saving stores the cleaned rich text.
 *
 * @extends BaseEntityService<WrittenDocument>
 */
final class WrittenDocumentService extends BaseEntityService implements WrittenDocumentServiceInterface
{
    private const OPEN_VISIT = [AppointmentStatus::CheckedIn, AppointmentStatus::InProgress, AppointmentStatus::Completed];

    public function __construct(
        ApplyListQuery $listQuery,
        private readonly DocumentTemplateServiceInterface $templates,
        private readonly PlaceholderFiller $filler,
        private readonly ClinicProfileServiceInterface $clinic,
        private readonly ClinicClock $clock,
    ) {
        parent::__construct($listQuery);
    }

    public function listWritten(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        return $this->list($query);
    }

    public function findWritten(string $id): WrittenDocument
    {
        return $this->find($id);
    }

    public function draft(Appointment $visit, WrittenDocumentKind $kind, ?string $templateId, ?string $body): string
    {
        $author = $this->assertAuthor($visit, $kind);
        $source = $templateId !== null ? $this->templates->findTemplate($templateId)->body : (string) $body;

        return RichText::sanitize($this->filler->fill($source, $visit, $author, $this->clinic->current()->name));
    }

    public function write(Appointment $visit, WrittenDocumentData $data): WrittenDocument
    {
        $author = $this->assertAuthor($visit, $data->kind);
        if ($data->templateId !== null) {
            $this->templates->findTemplate($data->templateId);
        }

        $document = new WrittenDocument([
            'appointment_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'author_id' => $author->id,
            'template_id' => $data->templateId,
            'kind' => $data->kind,
            'title' => $data->title,
            // Placeholders the author left in are filled now, so the saved text is final.
            'body' => RichText::sanitize($this->filler->fill($data->body, $visit, $author, $this->clinic->current()->name)),
            'issued_at' => $this->clock->now(),
        ]);
        $document->save();
        WrittenDocumentIssued::dispatch($document);

        return $this->findWritten($document->id);
    }

    /**
     * @return Builder<WrittenDocument>
     */
    protected function query(): Builder
    {
        return OwnershipScope::apply(WrittenDocument::query()->with(['patient.person', 'author.person']), 'written_documents', Auth::user());
    }

    /** The visit's own provider, after the patient arrived; a doctor unless the kind lets the session's nurse write it. */
    private function assertAuthor(Appointment $visit, WrittenDocumentKind $kind): Employee
    {
        /** @var User $user */
        $user = Auth::user();
        $isDoctor = $user->user_type === UserType::Doctor;
        $isNurse = $user->user_type === UserType::MedicalStaff;
        if ($visit->provider_id !== $user->id || ! ($isDoctor || ($isNurse && $kind->nurseMayWrite()))) {
            throw new WrittenDocumentNotAllowedException;
        }
        if (! in_array($visit->status, self::OPEN_VISIT, true)) {
            throw new InvalidStateTransitionException(__('errors.WRITTEN_DOCUMENT_NEEDS_VISIT'), ['status' => $visit->status->value]);
        }

        return Employee::query()->with('person')->findOrFail($user->id);
    }
}
