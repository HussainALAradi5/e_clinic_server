<?php

declare(strict_types=1);

namespace App\Interfaces\Shared;

/**
 * Base behaviour of every business record: string id, code, archive flag and soft delete.
 */
interface GenericEntity
{
    public function getId(): string;

    public function getCode(): ?string;

    public function isArchived(): bool;

    public function isDeleted(): bool;

    public function archive(): static;

    public function unarchive(): static;

    public function softDelete(): static;

    public function restore(): static;
}
