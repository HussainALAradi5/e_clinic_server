<?php

declare(strict_types=1);

namespace App\Traits\Shared;

use App\Enums\Shared\AuditEvent;
use App\Interfaces\Shared\AuditRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Writes audit_logs rows on created / updated / soft deleted / restored (only changed attributes, masked).
 * Archive and unarchive are plain updates of `is_archived`.
 *
 * @mixin Model
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => self::audit($model, AuditEvent::Created, [], $model->getAttributes()));

        static::updated(function (Model $model): void {
            // Soft delete / restore save `is_deleted` and are recorded by their own events below.
            if ($model->wasChanged('is_deleted')) {
                return;
            }
            $changes = $model->getChanges();
            self::audit($model, AuditEvent::Updated, Arr::only($model->getPrevious(), array_keys($changes)), $changes);
        });

        static::registerModelEvent('softDeleted', fn (Model $model) => self::audit($model, AuditEvent::Deleted, ['is_deleted' => false], ['is_deleted' => true]));
        static::registerModelEvent('restored', fn (Model $model) => self::audit($model, AuditEvent::Restored, ['is_deleted' => true], ['is_deleted' => false]));
    }

    /** @return list<string> */
    public function auditMasked(): array
    {
        return [];
    }

    /** @return list<string> */
    public function auditIgnored(): array
    {
        return [];
    }

    /** Module from the model's domain folder: App\Models\Payments\Invoice → `payments`. */
    public function auditModule(): string
    {
        $parts = explode('\\', static::class);

        return ($parts[1] ?? '') === 'Models' && count($parts) > 3 ? Str::snake($parts[2]) : 'shared';
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private static function audit(Model $model, AuditEvent $event, array $old, array $new): void
    {
        /** @var self&Model $model */
        $ignored = [...config('audit.ignored', []), ...$model->auditIgnored()];
        $new = Arr::except($new, $ignored);

        if ($new === [] || ! app()->bound(AuditRecorder::class)) {
            return;
        }

        app(AuditRecorder::class)->record($event, $model, Arr::except($old, $ignored), $new, $model->auditModule());
    }
}
