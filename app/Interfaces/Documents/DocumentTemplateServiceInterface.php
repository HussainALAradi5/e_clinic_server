<?php

declare(strict_types=1);

namespace App\Interfaces\Documents;

use App\Dtos\Documents\DocumentTemplateData;
use App\Models\Documents\DocumentTemplate;
use App\Utilities\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DocumentTemplateServiceInterface
{
    /** Ready-made templates and the reader's own. */
    public function listTemplates(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    public function findTemplate(string $id): DocumentTemplate;

    /** A new template written from scratch. */
    public function createTemplate(DocumentTemplateData $data): DocumentTemplate;

    /** Changes one of the reader's templates; ready-made ones are read-only. */
    public function updateTemplate(DocumentTemplate $template, DocumentTemplateData $data): DocumentTemplate;

    /** The reader's own editable copy of any template ("… (copy)"). */
    public function duplicateTemplate(DocumentTemplate $template): DocumentTemplate;

    public function deleteTemplate(DocumentTemplate $template): void;

    /**
     * Fields a template can name, for the editor's "Insert field" menu.
     *
     * @return list<array{key: string, token: string, label: string}>
     */
    public function placeholders(): array;
}
