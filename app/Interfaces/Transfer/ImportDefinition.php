<?php

declare(strict_types=1);

namespace App\Interfaces\Transfer;

use App\Dtos\Transfer\ImportColumn;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * How one resource is imported from Excel: the template columns, the row validation rules and how a
 * validated row is saved (through the resource's own service, so business rules still apply).
 */
interface ImportDefinition
{
    /**
     * @return list<ImportColumn>
     */
    public function columns(): array;

    /**
     * Laravel validation rules for one row, keyed by ImportColumn::$field.
     *
     * @return array<string, mixed>
     */
    public function rules(): array;

    /**
     * Saves one validated row. Throw a RendersApiError / ValidationException to reject the row.
     *
     * @param  array<string, mixed>  $row
     */
    public function import(array $row, Authenticatable $user): Model;
}
