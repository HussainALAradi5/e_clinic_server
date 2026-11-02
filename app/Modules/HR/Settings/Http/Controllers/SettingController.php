<?php

declare(strict_types=1);

namespace App\Modules\HR\Settings\Http\Controllers;

use App\Enums\HR\SettingKey;
use App\Modules\HR\Settings\Contracts\SettingServiceInterface;
use App\Modules\HR\Settings\Http\Resources\SettingResource;
use App\Modules\HR\Settings\Models\Setting;
use App\Shared\Http\Concerns\ApiResponse;
use App\Shared\Query\ListQuery;
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

    /** Switches a setting on / off (and sets the overtime pay per hour); creates the row when missing. */
    public function update(Request $request, SettingKey $key): JsonResponse
    {
        $request->validate([
            'is_enabled' => ['required', 'boolean'],
            'amount' => $key->hasAmount() ? ['nullable', 'numeric', 'min:0', 'max:1000', 'decimal:0,3'] : ['prohibited'],
        ]);
        $amount = $request->filled('amount') ? (string) $request->input('amount') : null;

        return $this->ok(new SettingResource($this->settings->save($key, $request->boolean('is_enabled'), $amount)));
    }
}
