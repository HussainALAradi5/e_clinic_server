<?php

declare(strict_types=1);

namespace App\Modules\Reference\Addresses\Http\Controllers;

use App\Modules\Reference\Addresses\Contracts\AddressServiceInterface;
use App\Modules\Reference\Addresses\Http\Resources\AddressResource;
use App\Shared\Http\Concerns\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AddressController
{
    use ApiResponse;

    public function __construct(private readonly AddressServiceInterface $addresses) {}

    public function show(string $address): JsonResponse
    {
        return $this->ok(new AddressResource($this->addresses->find($address)));
    }

    /** Printable address for invoices, letters and labels. */
    public function formatted(Request $request, string $address): JsonResponse
    {
        $input = $request->validate(['locale' => ['nullable', 'in:en,ar'], 'multiline' => ['nullable', 'boolean']]);
        $model = $this->addresses->find($address);

        return $this->ok(['text' => $this->addresses->format($model, (string) ($input['locale'] ?? 'en'), $request->boolean('multiline'))]);
    }
}
