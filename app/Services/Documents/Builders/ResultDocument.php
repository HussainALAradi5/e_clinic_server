<?php

declare(strict_types=1);

namespace App\Services\Documents\Builders;

use App\Dtos\Documents\Blocks\FactsBlock;
use App\Dtos\Documents\Blocks\ImagesBlock;
use App\Dtos\Documents\Blocks\NoticeBlock;
use App\Dtos\Documents\Blocks\SignatureBlock;
use App\Dtos\Documents\Blocks\TextBlock;
use App\Dtos\Documents\DocumentBadge;
use App\Dtos\Documents\DocumentFact;
use App\Dtos\Documents\DocumentImage;
use App\Dtos\Documents\DocumentPage;
use App\Enums\Appointments\ClinicalOrderType;
use App\Enums\Documents\DocumentTone;
use App\Enums\Identity\FileScanStatus;
use App\Interfaces\Documents\DocumentBuilder;
use App\Models\Appointments\ClinicalOrder;
use App\Models\Identity\Attachment;
use App\Utilities\Documents\DocumentFormat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A clinical order's result — X-ray, ultrasound, CT, MRI, lab test, ECG — or a referral letter: the type badge, who
 * ordered and reported it, the clinical question, the report, an abnormal call-out, the images (films, strips) and
 * the reporting doctor's signature.
 */
final class ResultDocument implements DocumentBuilder
{
    /** Images printed per result; more stay in the app. */
    private const MAX_IMAGES = 4;

    private const IMAGE_TYPES = ['image/png', 'image/jpeg', 'image/webp'];

    public function __construct(private readonly DocumentFormat $format) {}

    public function with(): array
    {
        return ['patient.person', 'orderedBy.person', 'orderedBy.specialty', 'reporter.person', 'performer.person', 'room',
            'referredToDoctor.person', 'referredToSpecialty', 'referredToCompany', 'attachments'];
    }

    public function build(Model $record): DocumentPage
    {
        /** @var ClinicalOrder $order */
        $order = $record;
        $f = $this->format;
        $referral = $order->type === ClinicalOrderType::Referral;
        $title = $referral ? $f->text('result.referral_title') : $f->text('result.title', ['type' => $order->type->label()]);
        $signer = $order->reporter ?? $order->orderedBy;

        return new DocumentPage(
            title: $title,
            reference: $order->code,
            fileName: $f->fileName($title, $order->code),
            badges: [new DocumentBadge($order->type->label(), DocumentTone::Info), $f->badge($order->status)],
            highlights: [
                new DocumentFact($f->text($referral ? 'result.referred' : 'result.ordered'), $f->date($order->created_at), tone: DocumentTone::Info),
                $order->completed_at ? new DocumentFact($f->text('result.completed'), $f->date($order->completed_at), tone: DocumentTone::Success) : null,
                $referral ? null : DocumentFact::optional($f->text('result.body_part'), $order->body_part),
                new DocumentFact($f->text('result.priority'), $order->priority->label(), tone: DocumentTone::Warning),
            ],
            blocks: [
                ...($order->is_abnormal === true ? [new NoticeBlock($f->text('result.abnormal'), $f->text('result.abnormal_text'), DocumentTone::Danger)] : []),
                new FactsBlock($f->text('common.patient'), $f->patient($order->patient)),
                new FactsBlock($f->text($referral ? 'result.referral' : 'result.examination'), $referral ? $this->referral($order) : $this->exam($order)),
                new TextBlock($f->text($referral ? 'result.reason' : 'result.question'), [$order->clinical_notes]),
                new TextBlock($f->text($referral ? 'result.summary' : 'result.report'), [$order->report ?? ($referral ? null : $f->text('result.not_ready'))]),
                new ImagesBlock($this->imagesHeading($order->type), $this->images($order)),
                new SignatureBlock($signer->person->full_name, $order->reporter ? $f->text('result.reporting_doctor') : $order->orderedBy->specialty->name, $signer->id, stamp: $referral),
            ],
            verifyNote: $f->text('result.verify'),
            issuedBy: $signer->person->full_name,
        );
    }

    /** @return list<DocumentFact|null> */
    private function exam(ClinicalOrder $order): array
    {
        $f = $this->format;

        return [
            new DocumentFact($f->text('result.ordered_by'), $order->orderedBy->person->full_name),
            DocumentFact::optional($f->text('result.performed_by'), $order->performer?->person->full_name),
            DocumentFact::optional($f->text('result.room'), $order->room?->name),
            DocumentFact::optional($f->text('result.reported_by'), $order->reporter?->person->full_name),
            DocumentFact::optional($f->text('result.started'), $order->started_at ? $f->dateTime($order->started_at) : null),
            DocumentFact::optional($f->text('result.completed'), $order->completed_at ? $f->dateTime($order->completed_at) : null),
        ];
    }

    /** @return list<DocumentFact|null> */
    private function referral(ClinicalOrder $order): array
    {
        $f = $this->format;

        return [
            new DocumentFact($f->text('result.referred_by'), $order->orderedBy->person->full_name),
            DocumentFact::optional($f->text('result.to_doctor'), $order->referredToDoctor?->person->full_name),
            DocumentFact::optional($f->text('result.specialty'), $order->referredToSpecialty?->name),
            DocumentFact::optional($f->text('result.facility'), $order->referredToCompany?->name),
        ];
    }

    private function imagesHeading(ClinicalOrderType $type): string
    {
        return $this->format->text(match ($type) {
            ClinicalOrderType::Ecg => 'result.tracing',
            ClinicalOrderType::LabTest, ClinicalOrderType::Referral => 'result.attached',
            default => 'result.images',
        });
    }

    /** @return list<DocumentImage> */
    private function images(ClinicalOrder $order): array
    {
        return array_values($order->attachments
            ->filter(fn (Attachment $a): bool => in_array($a->mime_type, self::IMAGE_TYPES, true) && ! $a->is_deleted && $a->scan_status !== FileScanStatus::Infected)
            ->take(self::MAX_IMAGES)
            ->map(fn (Attachment $a): ?DocumentImage => ($bytes = Storage::disk($a->disk)->get($a->path)) === null
                ? null
                : DocumentImage::fromBytes($bytes, $a->mime_type, $a->description ?: $a->original_name))
            ->filter()
            ->all());
    }
}
