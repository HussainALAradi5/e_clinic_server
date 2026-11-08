<?php

declare(strict_types=1);

namespace App\Http\Requests\HR;

use App\Dtos\HR\OvertimeBandData;
use App\Dtos\HR\SettingData;
use App\Enums\HR\SettingKey;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/** An HR switch; overtime also takes the pay per hour and its time bands. */
final class UpdateSettingRequest extends FormRequest
{
    private const MAX_BANDS = 12;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $hasAmount = $this->settingKey()->hasAmount();
        $money = ['numeric', 'min:0', 'max:1000', 'decimal:0,3'];

        return [
            'is_enabled' => ['required', 'boolean'],
            'amount' => $hasAmount ? ['nullable', ...$money] : ['prohibited'],
            'bands' => $hasAmount ? ['sometimes', 'array', 'max:'.self::MAX_BANDS] : ['prohibited'],
            'bands.*.start' => ['required', 'date_format:H:i'],
            'bands.*.end' => ['required', 'date_format:H:i'],
            'bands.*.rate' => ['required', ...$money],
        ];
    }

    /**
     * Each band must end after it starts, and bands may not overlap.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $bands = $this->collect('bands')->sortBy('start');
            $previousEnd = null;
            foreach ($bands as $index => $band) {
                if ($band['end'] <= $band['start']) {
                    $validator->errors()->add("bands.{$index}.end", __('The band must end after it starts.'));
                } elseif ($previousEnd !== null && $band['start'] < $previousEnd) {
                    $validator->errors()->add("bands.{$index}.start", __('Time bands cannot overlap.'));
                }
                $previousEnd = $band['end'];
            }
        }];
    }

    public function toData(): SettingData
    {
        $bands = $this->has('bands')
            ? array_values(array_map(OvertimeBandData::fromArray(...), (array) $this->input('bands')))
            : null;

        return new SettingData($this->boolean('is_enabled'), $this->filled('amount') ? (string) $this->input('amount') : null, $bands);
    }

    private function settingKey(): SettingKey
    {
        $key = $this->route('key');

        return $key instanceof SettingKey ? $key : SettingKey::from((string) $key);
    }
}
