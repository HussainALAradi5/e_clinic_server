<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Dtos\Documents\DocumentTemplateData;
use App\Enums\Documents\TemplatePlaceholder;
use App\Exceptions\Documents\TemplateReadOnlyException;
use App\Interfaces\Documents\DocumentTemplateServiceInterface;
use App\Models\Documents\DocumentTemplate;
use App\Services\Base\BaseEntityService;
use App\Utilities\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Document templates: the clinic's ready-made ones (read-only, copy to change) and the ones staff write themselves,
 * in rich text with {{placeholders}}. Each reader sees the ready-made templates plus their own.
 *
 * @extends BaseEntityService<DocumentTemplate>
 */
final class DocumentTemplateService extends BaseEntityService implements DocumentTemplateServiceInterface
{
    public function listTemplates(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        return $this->list($query);
    }

    public function findTemplate(string $id): DocumentTemplate
    {
        return $this->find($id);
    }

    public function createTemplate(DocumentTemplateData $data): DocumentTemplate
    {
        $template = new DocumentTemplate([...$data->attributes(), 'is_system' => false]);
        $template->save();

        return $template->refresh();
    }

    public function updateTemplate(DocumentTemplate $template, DocumentTemplateData $data): DocumentTemplate
    {
        $this->assertEditable($template);
        $template->update($data->attributes());

        return $template->refresh();
    }

    public function duplicateTemplate(DocumentTemplate $template): DocumentTemplate
    {
        $copy = $template->replicate(['code', 'is_system', 'created_by', 'updated_by']);
        $copy->forceFill(['name' => mb_substr(__('documents.templates.copy_of', ['name' => $template->name]), 0, 120), 'is_system' => false, 'is_active' => true])->save();

        return $copy->refresh();
    }

    public function deleteTemplate(DocumentTemplate $template): void
    {
        $this->assertEditable($template);
        $this->delete($template);
    }

    public function placeholders(): array
    {
        return array_map(fn (TemplatePlaceholder $p): array => ['key' => $p->value, 'token' => $p->token(), 'label' => $p->label()], TemplatePlaceholder::cases());
    }

    /**
     * Ready-made templates (active) and the reader's own.
     *
     * @return Builder<DocumentTemplate>
     */
    protected function query(): Builder
    {
        $userId = Auth::id();

        return DocumentTemplate::query()->where(fn (Builder $q) => $q->where(fn (Builder $s) => $s->where('is_system', true)->where('is_active', true))
            ->orWhere('created_by', $userId));
    }

    protected function beforeDelete(Model $model): void
    {
        $this->assertEditable($model);
    }

    private function assertEditable(Model $template): void
    {
        if ($template instanceof DocumentTemplate && $template->is_system) {
            throw new TemplateReadOnlyException;
        }
    }
}
