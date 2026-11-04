<?php

declare(strict_types=1);

namespace App\Services\Documents\Builders;

use App\Dtos\Documents\Blocks\FactsBlock;
use App\Dtos\Documents\Blocks\NoticeBlock;
use App\Dtos\Documents\Blocks\SignatureBlock;
use App\Dtos\Documents\Blocks\TextBlock;
use App\Dtos\Documents\DocumentBadge;
use App\Dtos\Documents\DocumentFact;
use App\Dtos\Documents\DocumentPage;
use App\Enums\Documents\DocumentTone;
use App\Interfaces\Documents\DocumentBuilder;
use App\Models\Appointments\PatientConsent;
use App\Utilities\Documents\DocumentFormat;
use Illuminate\Database\Eloquent\Model;

/**
 * Signed consent form: what was consented to, who signed and who explained it, validity and any revocation.
 */
final class ConsentDocument implements DocumentBuilder
{
    public function __construct(private readonly DocumentFormat $format) {}

    public function with(): array
    {
        return ['patient.person', 'explainer.person', 'witness.person'];
    }

    public function build(Model $record): DocumentPage
    {
        /** @var PatientConsent $consent */
        $consent = $record;
        $f = $this->format;
        $revoked = $consent->revoked_at !== null;
        $explainer = $consent->explainer->person->full_name;

        return new DocumentPage(
            title: $f->text('consent.title', ['type' => $consent->type->label()]),
            reference: $consent->code,
            fileName: $f->fileName($f->text('consent.file'), $consent->code),
            badges: [$revoked
                ? new DocumentBadge($f->text('consent.revoked'), DocumentTone::Danger)
                : new DocumentBadge($f->text('consent.signed'), DocumentTone::Success)],
            highlights: [
                new DocumentFact($f->text('consent.signed'), $f->dateTime($consent->signed_at), tone: DocumentTone::Info),
                new DocumentFact($f->text('consent.valid_until'), $consent->valid_until ? $f->date($consent->valid_until) : $f->text('consent.until_revoked'), tone: DocumentTone::Warning),
                new DocumentFact($f->text('consent.signed_by'), $consent->signed_by_name, tone: DocumentTone::Violet),
            ],
            blocks: [
                ...($revoked ? [new NoticeBlock($f->text('consent.revoked_on', ['at' => $f->dateTime($consent->revoked_at)]), (string) $consent->revocation_reason, DocumentTone::Danger)] : []),
                new FactsBlock($f->text('common.patient'), $f->patient($consent->patient, withCpr: true)),
                new TextBlock($f->text('consent.consent'), [$consent->description]),
                new FactsBlock($f->text('consent.signatures'), [
                    new DocumentFact($f->text('consent.signed_by'), $consent->signed_by_name.' ('.$consent->signer_relationship.')'),
                    new DocumentFact($f->text('consent.explained_by'), $explainer),
                    DocumentFact::optional($f->text('consent.witness'), $consent->witness?->person->full_name),
                ]),
                new SignatureBlock($explainer, $consent->explainer->job_title, $consent->explained_by),
            ],
            verifyNote: $f->text('consent.verify'),
            issuedBy: $explainer,
        );
    }
}
