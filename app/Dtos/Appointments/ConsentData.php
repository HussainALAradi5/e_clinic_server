<?php

declare(strict_types=1);

namespace App\Dtos\Appointments;

use Illuminate\Support\Arr;

final readonly class ConsentData
{
    public const FIELDS = ['type', 'appointment_id', 'treatment_id', 'clinical_order_id', 'description', 'signed_by_name', 'signer_relationship', 'explained_by', 'witness_id', 'signed_at', 'valid_until'];

    /**
     * @param  array<string, mixed>  $values
     */
    private function __construct(public array $values, public ?string $signatureAttachmentId) {}

    /**
     * @param  array<string, mixed>  $input  validated snake_case input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            array_map(fn (mixed $v): mixed => $v === '' ? null : $v, Arr::only($input, self::FIELDS)),
            isset($input['signature_attachment_id']) ? (string) $input['signature_attachment_id'] : null,
        );
    }
}
