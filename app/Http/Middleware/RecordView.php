<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Identity\AuditAction;
use App\Interfaces\Identity\AuditLogServiceInterface;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds "opened the page" to a record's event log when someone other than the record's owner opens it. Works from the
 * route name (config `audit.viewed_routes`), so the web and mobile apps need no code and it cannot be skipped. Repeat
 * opens by the same person within `audit.view_dedupe_seconds` count once.
 */
final class RecordView
{
    public function __construct(private readonly AuditLogServiceInterface $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $alias = Config::array('audit.viewed_routes')[$request->route()?->getName() ?? ''] ?? null;
        $user = $request->user();

        if (is_string($alias) && $user !== null && $request->isMethod('GET') && $response->getStatusCode() === Response::HTTP_OK) {
            $this->record($alias, $this->recordId($request), (string) $user->getAuthIdentifier());
        }

        return $response;
    }

    private function recordId(Request $request): string
    {
        $parameters = $request->route()?->parameters() ?? [];
        $last = end($parameters);

        return $last instanceof Model ? (string) $last->getKey() : (string) $last;
    }

    private function record(string $alias, string $id, string $userId): void
    {
        $class = Config::array('audit.models')[$alias] ?? null;

        if (! is_string($class) || ! is_a($class, Model::class, true) || ! Str::isUuid($id) || $id === $userId) {
            return;
        }

        if (Cache::add("audit:viewed:{$userId}:{$alias}:{$id}", true, Config::integer('audit.view_dedupe_seconds'))) {
            $model = new $class;
            $model->setAttribute($model->getKeyName(), $id);
            $this->audit->record(AuditAction::Viewed, $model);
        }
    }
}
