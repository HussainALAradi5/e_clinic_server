<?php

declare(strict_types=1);

namespace App\Http\Controllers\Transactions;

use App\Dtos\Transactions\PayExpenseData;
use App\Enums\Shared\PaymentMethod;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Transactions\ExpenseRequest;
use App\Http\Resources\Transactions\ExpenseResource;
use App\Interfaces\Transactions\ExpenseServiceInterface;
use App\Models\Transactions\Expense;
use App\Utilities\Query\ListQuery;
use App\Utilities\Shared\ClinicClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

final class ExpenseController
{
    use ApiResponse;

    public function __construct(private readonly ExpenseServiceInterface $expenses) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, Expense::listQueryConfig());

        return $this->paginated(ExpenseResource::collection($this->expenses->list($query)), $query);
    }

    public function show(string $expense): JsonResponse
    {
        return $this->ok(new ExpenseResource($this->expenses->find($expense)));
    }

    public function store(ExpenseRequest $request): JsonResponse
    {
        return $this->created(new ExpenseResource($this->expenses->create($request->toData())));
    }

    public function update(ExpenseRequest $request, string $expense): JsonResponse
    {
        return $this->ok(new ExpenseResource($this->expenses->update($this->expenses->find($expense), $request->toData(), $request->integer('version'))));
    }

    public function submit(string $expense): JsonResponse
    {
        return $this->ok(new ExpenseResource($this->expenses->submit($this->expenses->find($expense))));
    }

    public function approve(string $expense): JsonResponse
    {
        return $this->ok(new ExpenseResource($this->expenses->approve($this->expenses->find($expense))));
    }

    public function reject(Request $request, string $expense): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->ok(new ExpenseResource($this->expenses->reject($this->expenses->find($expense), $request->string('reason')->toString())));
    }

    public function pay(Request $request, string $expense, ClinicClock $clock): JsonResponse
    {
        $request->validate([
            'method' => ['required', Rule::enum(PaymentMethod::class)->except([PaymentMethod::Insurance])],
            'reference' => [Rule::requiredIf(fn (): bool => $request->input('method') !== PaymentMethod::Cash->value), 'nullable', 'string', 'max:100'],
        ]);
        $data = new PayExpenseData($clock->today(), PaymentMethod::from($request->string('method')->toString()), $request->filled('reference') ? $request->string('reference')->toString() : null);

        return $this->ok(new ExpenseResource($this->expenses->pay($this->expenses->find($expense), $data)));
    }

    public function cancel(string $expense): JsonResponse
    {
        return $this->ok(new ExpenseResource($this->expenses->cancel($this->expenses->find($expense))));
    }

    public function destroy(string $expense): Response
    {
        $this->expenses->delete($this->expenses->find($expense));

        return $this->noContent();
    }

    public function restore(string $expense): JsonResponse
    {
        return $this->ok(new ExpenseResource($this->expenses->restore($this->expenses->findWithDeleted($expense))));
    }
}
