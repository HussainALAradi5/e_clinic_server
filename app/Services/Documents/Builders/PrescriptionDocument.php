<?php

declare(strict_types=1);

namespace App\Services\Documents\Builders;

use App\Dtos\Documents\Blocks\FactsBlock;
use App\Dtos\Documents\Blocks\NoticeBlock;
use App\Dtos\Documents\Blocks\SignatureBlock;
use App\Dtos\Documents\Blocks\TableBlock;
use App\Dtos\Documents\Blocks\TextBlock;
use App\Dtos\Documents\DocumentFact;
use App\Dtos\Documents\DocumentPage;
use App\Enums\Documents\DocumentTone;
use App\Enums\Shared\GenericStatus;
use App\Interfaces\Documents\DocumentBuilder;
use App\Models\Pharmacy\Prescription;
use App\Models\Pharmacy\PrescriptionItem;
use App\Utilities\Documents\DocumentFormat;
use Illuminate\Database\Eloquent\Model;

/**
 * Prescription (Rx): patient, prescriber, one row per medicine with dose, frequency, route and duration, signed.
 */
final class PrescriptionDocument implements DocumentBuilder
{
    public function __construct(private readonly DocumentFormat $format) {}

    public function with(): array
    {
        return ['patient.person', 'doctor.person', 'doctor.specialty', 'items.medication'];
    }

    public function build(Model $record): DocumentPage
    {
        /** @var Prescription $rx */
        $rx = $record;
        $f = $this->format;
        $doctor = $rx->doctor->person->full_name;

        return new DocumentPage(
            title: $f->text('prescription.title'),
            reference: $rx->code,
            fileName: $f->fileName($f->text('prescription.title'), $rx->code),
            badges: [$f->badge($rx->status)],
            highlights: [
                new DocumentFact($f->text('prescription.prescribed'), $f->date($rx->created_at), tone: DocumentTone::Info),
                new DocumentFact($f->text('prescription.valid_until'), $f->date($rx->valid_until), tone: DocumentTone::Warning),
                new DocumentFact($f->text('prescription.medicines'), (string) $rx->items->count(), tone: DocumentTone::Violet),
                new DocumentFact($f->text('prescription.prescriber'), $doctor),
            ],
            blocks: [
                ...($rx->status === GenericStatus::Cancelled ? [new NoticeBlock($f->text('prescription.cancelled'), (string) $rx->cancellation_reason, DocumentTone::Danger)] : []),
                new FactsBlock($f->text('common.patient'), $f->patient($rx->patient)),
                new TableBlock($f->text('prescription.medicines'), $f->columns('prescription.columns'), $rx->items->map(fn (PrescriptionItem $i): array => [
                    trim($i->medication->generic_name.' '.$i->medication->strength).($i->medication->brand_name ? " ({$i->medication->brand_name})" : '').($i->instructions ? "\n".$i->instructions : ''),
                    $i->dose,
                    $i->frequency,
                    $i->route->label(),
                    (string) $i->duration_days,
                    $i->quantity,
                ])->all(), numeric: [4, 5]),
                new TextBlock($f->text('common.notes'), [$rx->notes]),
                new SignatureBlock($doctor, $rx->doctor->specialty->name, $rx->doctor_id),
            ],
            verifyNote: $f->text('prescription.verify'),
            issuedBy: $doctor,
        );
    }
}
