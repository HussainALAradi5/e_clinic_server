<?php

declare(strict_types=1);

namespace App\Http\Controllers\HR;

use App\Enums\HR\SettingKey;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\HR\UpdateSettingRequest;
use App\Http\Resources\HR\SettingResource;
use App\Interfaces\HR\SettingServiceInterface;
use App\Models\HR\Setting;
use App\Utilities\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SettingController
{
    use ApiResponse;

    public function __construct(private readonly SettingServiceInterface $settings) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, Setting::listQueryConfig());

        return $this->paginated(SettingResource::collection($this->settings->list($query)), $query);
    }

    public function show(SettingKey $key): JsonResponse
    {
        return $this->ok(new SettingResource($this->settings->find($key)));
    }

    /** Switches a setting on / off and, for overtime, sets the pay per hour and its time bands; creates the row when missing. */
    public function update(UpdateSettingRequest $request, SettingKey $key): JsonResponse
    {
        return $this->ok(new SettingResource($this->settings->save($key, $request->toData())));
    }
}
