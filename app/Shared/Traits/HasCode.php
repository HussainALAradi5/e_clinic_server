<?php

declare(strict_types=1);

namespace App\Shared\Traits;

use App\Shared\Support\DocumentNumberGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fills `code` on creating with the next document number (`PAT-2026-000123`) unless one was provided.
 * Models with manually entered codes (rooms, accounts) override usesManualCode().
 *
 * @mixin Model
 */
trait HasCode
{
    /** Printed prefix, e.g. 'PAT'. */
    abstract public static function codePrefix(): string;

    /** Key in document_sequences; defaults to the snake-cased model name. */
    public static function codeType(): string
    {
        return Str::snake(class_basename(static::class));
    }

    /** Documents restart every year (`INV-2026-000001`); master data keeps one sequence (`PRS-000001`). */
    public static function codeResetsYearly(): bool
    {
        return true;
    }

    public static function usesManualCode(): bool
    {
        return false;
    }

    public static function bootHasCode(): void
    {
        static::creating(function (self $model): void {
            if (filled($model->getAttribute('code')) || static::usesManualCode()) {
                return;
            }

            $model->setAttribute('code', DB::transaction(
                fn (): string => app(DocumentNumberGenerator::class)->next(static::codeType(), static::codePrefix(), yearly: static::codeResetsYearly()),
            ));
        });
    }
}
