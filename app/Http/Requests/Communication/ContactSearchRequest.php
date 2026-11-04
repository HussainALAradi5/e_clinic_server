<?php

declare(strict_types=1);

namespace App\Http\Requests\Communication;

use App\Dtos\Communication\ContactSearch;
use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /chat/contacts — search colleagues by name, phone, email or staff no., one page at a time.
 */
final class ContactSearchRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.ContactSearch::MAX_PER_PAGE],
        ];
    }

    public function toData(): ContactSearch
    {
        return new ContactSearch(
            $this->string('search')->trim()->toString() ?: null,
            $this->integer('page', 1),
            $this->integer('per_page', ContactSearch::DEFAULT_PER_PAGE),
        );
    }
}
