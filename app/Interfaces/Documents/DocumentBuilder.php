<?php

declare(strict_types=1);

namespace App\Interfaces\Documents;

use App\Dtos\Documents\DocumentPage;
use Illuminate\Database\Eloquent\Model;

/**
 * Lays out one document type: turns its record into the title, key facts and blocks of the shared PDF template.
 */
interface DocumentBuilder
{
    /**
     * Relations to eager-load before build().
     *
     * @return list<string>
     */
    public function with(): array;

    public function build(Model $record): DocumentPage;
}
