<?php

declare(strict_types=1);

namespace App\Modules\Reference\Countries\Http\Controllers;

use App\Modules\Reference\Countries\Contracts\CountryServiceInterface;
use App\Modules\Reference\Countries\Http\Requests\UpdateCountryRequest;
use App\Modules\Reference\Countries\Http\Resources\CountryResource;
use App\Modules\Reference\Countries\Models\Country;
use App\Shared\Http\Concerns\ApiResponse;
use App\Shared\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CountryController
{
    use ApiResponse;

    public function __construct(private readonly CountryServiceInterface $countries) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['include_inactive' => ['nullable', 'boolean']]);
        $query = ListQuery::fromRequest($request, Country::listQueryConfig());

        return $this->paginated(CountryResource::collection($this->countries->list($query, $request->boolean('include_inactive'))), $query);
    }

    /** Every active country in one response (pickers; also public for portal registration). */
    public function options(): JsonResponse
    {
        return $this->ok(CountryResource::collection($this->countries->options()));
    }

    public function show(string $country): JsonResponse
    {
        return $this->ok(new CountryResource($this->countries->find($country)));
    }

    public function update(UpdateCountryRequest $request, string $country): JsonResponse
    {
        return $this->ok(new CountryResource($this->countries->update($this->countries->find($country), $request->toData())));
    }
}
