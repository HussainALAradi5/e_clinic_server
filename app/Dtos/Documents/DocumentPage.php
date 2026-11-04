<?php

declare(strict_types=1);

namespace App\Dtos\Documents;

use App\Interfaces\Documents\DocumentBlock;

/**
 * Everything the shared PDF template needs: title, reference, badges, the key facts under the title and the body
 * blocks in order, and who issued it (the doctor who wrote it, the cashier who took it…). The verification QR and
 * who printed it are added by the service. `showReference` false keeps the internal record code off the paper
 * (e.g. a sick leave the patient hands to an employer; the QR still checks it).
 */
final readonly class DocumentPage
{
    /** @var list<DocumentFact> */
    public array $highlights;

    /**
     * @param  list<DocumentBadge>  $badges
     * @param  list<DocumentFact|null>  $highlights  3–4 key facts shown as tiles under the title (nulls dropped)
     * @param  list<DocumentBlock>  $blocks
     */
    public function __construct(
        public string $title,
        public string $reference,
        public string $fileName,
        public array $badges = [],
        array $highlights = [],
        public array $blocks = [],
        public string $verifyNote = '',
        public ?string $issuedBy = null,
        public bool $showReference = true,
    ) {
        $this->highlights = array_values(array_filter($highlights));
    }
}
