<?php

declare(strict_types=1);

namespace App\Modules\Transactions\CashSessions\Http\Controllers;

use App\Modules\Transactions\CashSessions\Contracts\CashSessionServiceInterface;
use App\Modules\Transactions\CashSessions\Data\DepositData;
use App\Modules\Transactions\CashSessions\Http\Requests\CloseSessionRequest;
use App\Modules\Transactions\CashSessions\Http\Resources\CashSessionResource;
use App\Modules\Transactions\CashSessions\Models\CashSession;
use App\Shared\Http\Concerns\ApiResponse;
use App\Shared\Query\ListQuery;
use App\Shared\Support\ClinicClock;
use App\Shared\Support\MoneyHelper;
use Carbon\CarbonImmutable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CashSessionController
{
    use ApiResponse;

    public function __construct(private readonly CashSessionServiceInterface $sessions) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, CashSession::listQueryConfig());

        return $this->paginated(CashSessionResource::collection($this->sessions->list($query)), $query);
    }

    /** The caller's open (or closing) drawer with its running figures; `data: null` when none is open. */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        $session = $user === null ? null : $this->sessions->current($user);

        return $this->ok($session === null ? null : $this->withReport($session));
    }

    public function show(string $cashSession): JsonResponse
    {
        return $this->ok($this->withReport($this->sessions->find($cashSession)));
    }

    public function open(Request $request): JsonResponse
    {
        $request->validate(['opening_float' => ['required', 'decimal:0,3', 'min:0']]);
        $user = $request->user() ?? throw new AuthenticationException;
        $session = $this->sessions->open($user, MoneyHelper::of($request->string('opening_float')->toString()));

        return $this->created($this->withReport($session));
    }

    public function close(CloseSessionRequest $request, string $cashSession): JsonResponse
    {
        return $this->ok($this->withReport($this->sessions->close($this->sessions->find($cashSession), $request->toData())));
    }

    public function approveDifference(Request $request, string $cashSession): JsonResponse
    {
        $request->validate(['notes' => ['required', 'string', 'max:500']]);

        return $this->ok($this->withReport($this->sessions->approveDifference($this->sessions->find($cashSession), $request->string('notes')->toString())));
    }

    public function deposit(Request $request, string $cashSession, ClinicClock $clock): JsonResponse
    {
        $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'decimal:0,3', 'gt:0'],
        ]);
        $data = new DepositData(
            CarbonImmutable::parse($request->string('date')->toString(), $clock->timezone())->startOfDay(),
            $request->string('reference')->toString(),
            $request->string('amount')->toString(),
        );

        return $this->ok($this->withReport($this->sessions->deposit($this->sessions->find($cashSession), $data)));
    }

    private function withReport(CashSession $session): CashSessionResource
    {
        return (new CashSessionResource($session))->withReport($this->sessions->report($session));
    }
}
