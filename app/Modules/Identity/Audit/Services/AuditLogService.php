<?php

declare(strict_types=1);

namespace App\Modules\Identity\Audit\Services;

use App\Enums\Identity\AuditAction;
use App\Modules\Identity\Audit\Contracts\AuditLogServiceInterface;
use App\Modules\Identity\Audit\Jobs\WriteAuditLog;
use App\Modules\Identity\Audit\Models\AuditLog;
use App\Shared\Access\OwnershipScope;
use App\Shared\Contracts\Auditable;
use App\Shared\Http\Middleware\AssignRequestId;
use App\Shared\Query\ApplyListQuery;
use App\Shared\Query\ListQuery;
use App\Shared\Support\ClinicClock;
use BackedEnum;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class AuditLogService implements AuditLogServiceInterface
{
    public const MASK = '***';

    public function __construct(
        private readonly ApplyListQuery $listQuery,
        private readonly ClinicClock $clock,
    ) {}

    public function record(BackedEnum $action, ?Model $model, array $old = [], array $new = [], ?string $module = null, ?string $userId = null): AuditLog
    {
        $masked = array_values(array_map('strval', [...Config::array('audit.masked'), ...($model instanceof Auditable ? $model->auditMasked() : [])]));
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();
        $requestId = $request?->attributes->get(AssignRequestId::ATTRIBUTE);

        $attributes = [
            'id' => (string) Str::uuid7(),
            'user_id' => $userId ?? Auth::id(),
            'action' => AuditAction::from((string) $action->value)->value,
            'auditable_type' => $model?->getMorphClass(),
            'auditable_id' => $model?->getKey(),
            'module' => $module ?? ($model instanceof Auditable ? $model->auditModule() : 'identity'),
            'old_values' => $this->clean($old, $masked),
            'new_values' => $this->clean($new, $masked),
            'ip_address' => $request?->ip(),
            'user_agent' => $request !== null ? mb_substr((string) $request->userAgent(), 0, 512) ?: null : null,
            'request_id' => is_string($requestId) && Str::isUuid($requestId) ? $requestId : null,
            'created_at' => $this->clock->now(),
        ];

        try {
            WriteAuditLog::dispatch($attributes)->onQueue(Config::get('audit.queue'))->afterCommit();
        } catch (Throwable $e) {
            // Queue unavailable: never lose the trail — write synchronously.
            Log::warning('Audit queue unavailable, writing synchronously.', ['exception' => $e->getMessage()]);
            (new WriteAuditLog($attributes))->handle();
        }

        return (new AuditLog)->forceFill($attributes);
    }

    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        $builder = OwnershipScope::apply(AuditLog::query()->with('user.person'), 'audit', Auth::user());

        return $this->listQuery->paginate($builder, $query);
    }

    public function forModel(string $type, string $id): Collection
    {
        $class = Config::array('audit.models')[$type] ?? null;

        if (! is_string($class) || ! is_subclass_of($class, Model::class)) {
            throw (new ModelNotFoundException)->setModel(AuditLog::class);
        }

        return OwnershipScope::apply(AuditLog::query()->with('user.person'), 'audit', Auth::user())
            ->where('auditable_type', (new $class)->getMorphClass())
            ->where('auditable_id', $id)
            ->orderByDesc('id')
            ->get()
            ->toBase();
    }

    /**
     * JSON-safe values with sensitive keys masked; null when empty.
     *
     * @param  array<string, mixed>  $values
     * @param  list<string>  $masked
     * @return array<string, mixed>|null
     */
    private function clean(array $values, array $masked): ?array
    {
        if ($values === []) {
            return null;
        }

        $clean = [];
        foreach ($values as $key => $value) {
            $clean[$key] = match (true) {
                in_array($key, $masked, true) => $value === null ? null : self::MASK,
                $value instanceof BackedEnum => $value->value,
                $value instanceof \DateTimeInterface => $value->format(DATE_ATOM),
                is_scalar($value), $value === null, is_array($value) => $value,
                default => (string) json_encode($value),
            };
        }

        return $clean;
    }
}
