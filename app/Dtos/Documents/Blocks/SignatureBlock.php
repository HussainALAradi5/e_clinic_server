<?php

declare(strict_types=1);

namespace App\Dtos\Documents\Blocks;

use App\Interfaces\Documents\DocumentBlock;

/**
 * The signer's name and role over their signature (drawn or uploaded, when they keep one), beside the clinic stamp.
 * Builders name the signer (`signerId`, a person id); the document service fills the two images before printing.
 */
final readonly class SignatureBlock implements DocumentBlock
{
    public function __construct(
        public string $name,
        public string $role,
        public ?string $signerId = null,
        public bool $stamp = true,
        public ?string $signatureImage = null,
        public ?string $stampImage = null,
    ) {}

    /** A copy with the signature and stamp images (data URIs, null when there is none). */
    public function withImages(?string $signature, ?string $stamp): self
    {
        return new self($this->name, $this->role, $this->signerId, $this->stamp, $signature, $this->stamp ? $stamp : null);
    }

    public function view(): string
    {
        return 'pdf.blocks.signature';
    }
}
