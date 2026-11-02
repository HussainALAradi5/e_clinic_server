<?php

declare(strict_types=1);

namespace App\Traits\Shared;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Fills `created_by` / `updated_by` from the authenticated user.
 *
 * @mixin Model
 */
trait Blameable
{
    public static function bootBlameable(): void
    {
        static::creating(function (self $model): void {
            $model->setAttribute('created_by', $model->getAttribute('created_by') ?? Auth::id());
            $model->setAttribute('updated_by', $model->getAttribute('updated_by') ?? Auth::id());
        });

        static::updating(function (self $model): void {
            if (Auth::check()) {
                $model->setAttribute('updated_by', Auth::id());
            }
        });
    }
}
