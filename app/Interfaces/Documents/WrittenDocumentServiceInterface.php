<?php

declare(strict_types=1);

namespace App\Interfaces\Documents;

use App\Dtos\Documents\WrittenDocumentData;
use App\Enums\Documents\WrittenDocumentKind;
use App\Models\Appointments\Appointment;
use App\Models\Documents\WrittenDocument;
use App\Utilities\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface WrittenDocumentServiceInterface
{
    /** @return LengthAwarePaginator<int, WrittenDocument>|CursorPaginator<int, WrittenDocument> */
    public function listWritten(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    public function findWritten(string $id): WrittenDocument;

    /**
     * Ready-to-edit text for the visit ("auto generate"): the template's body, or the given body, with every
     * placeholder filled from the visit. Nothing is saved.
     */
    public function draft(Appointment $visit, WrittenDocumentKind $kind, ?string $templateId, ?string $body): string;

    /** Saves the document written at the visit by its provider. */
    public function write(Appointment $visit, WrittenDocumentData $data): WrittenDocument;
}
