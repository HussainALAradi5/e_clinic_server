<?php

declare(strict_types=1);

namespace App\Services\Documents\Builders;

use App\Dtos\Documents\Blocks\FactsBlock;
use App\Dtos\Documents\Blocks\RichTextBlock;
use App\Dtos\Documents\Blocks\SignatureBlock;
use App\Dtos\Documents\DocumentBadge;
use App\Dtos\Documents\DocumentFact;
use App\Dtos\Documents\DocumentPage;
use App\Enums\Documents\DocumentTone;
use App\Interfaces\Documents\DocumentBuilder;
use App\Models\Documents\WrittenDocument;
use App\Utilities\Documents\DocumentFormat;
use Illuminate\Database\Eloquent\Model;

/**
 * A document written at a visit (report, certificate, referral note, letter): its kind, the patient card, the
 * author's rich text, then their signature beside the clinic stamp.
 */
final class WrittenDocumentBuilder implements DocumentBuilder
{
    public function __construct(private readonly DocumentFormat $format) {}

    public function with(): array
    {
        return ['patient.person', 'author.person', 'appointment'];
    }

    public function build(Model $record): DocumentPage
    {
        /** @var WrittenDocument $document */
        $document = $record;
        $f = $this->format;
        $author = $document->author->person->full_name;

        return new DocumentPage(
            title: $document->title,
            reference: $document->code,
            fileName: $f->fileName($document->title, $document->code),
            badges: [new DocumentBadge($document->kind->label(), DocumentTone::Info)],
            highlights: [
                new DocumentFact($f->text('written.issued'), $f->date($document->issued_at), tone: DocumentTone::Info),
                new DocumentFact($f->text('written.visit'), $f->date($document->appointment->scheduled_date), tone: DocumentTone::Violet),
                new DocumentFact($f->text('written.author'), $author, tone: DocumentTone::Success),
            ],
            blocks: [
                new FactsBlock($f->text('common.patient'), $f->patient($document->patient, withCpr: true)),
                new RichTextBlock($document->body),
                new SignatureBlock($author, $document->author->job_title, $document->author_id),
            ],
            verifyNote: $f->text('written.verify'),
            issuedBy: $author,
        );
    }
}
