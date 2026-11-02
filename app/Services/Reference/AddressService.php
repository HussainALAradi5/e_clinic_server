<?php

declare(strict_types=1);

namespace App\Services\Reference;

use App\Dtos\Reference\AddressData;
use App\Exceptions\Reference\InvalidAddressException;
use App\Interfaces\Reference\AddressServiceInterface;
use App\Interfaces\Reference\CountryServiceInterface;
use App\Models\Reference\Address;
use App\Utilities\Access\PermissionExpression;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

final class AddressService implements AddressServiceInterface
{
    /** Fields each country format requires (others: at least line 1 or a city). */
    private const REQUIRED = ['BH' => ['building', 'road', 'block']];

    public function __construct(private readonly CountryServiceInterface $countries) {}

    public function save(?Address $current, AddressData $data): Address
    {
        $this->validate($data);

        $address = $current ?? new Address;
        $address->fill($data->values)->save();

        return $address->load('country');
    }

    public function remove(?Address $address): void
    {
        $address?->delete();
    }

    public function find(string $id): Address
    {
        $address = Address::query()->with('country')->findOrFail($id);

        foreach ((array) Config::get('reference.address_owners') as $model => $resource) {
            /** @var class-string<Model> $model */
            $owner = $model::query()->where('address_id', $id)->first();
            if ($owner !== null && PermissionExpression::parse("{$resource}.read_all|{$resource}.read_own")->evaluate(Auth::user(), $owner)) {
                return $address;
            }
        }

        throw (new ModelNotFoundException)->setModel(Address::class);
    }

    public function format(Address $address, string $locale = 'en', bool $multiline = false): string
    {
        $ar = $locale === 'ar';
        $label = fn (string $key, ?string $value): ?string => $value === null ? null : (string) trans_choice("reference.address.{$key}", 1, ['value' => $value], $locale);

        $lines = $address->country_code === 'BH'
            ? [
                implode($ar ? '، ' : ', ', array_filter([$label('flat', $address->flat), $label('building', $address->building)])),
                implode($ar ? '، ' : ', ', array_filter([$label('road', $address->road), $label('block', $address->block)])),
                $address->area,
            ]
            : [$address->line1, $address->line2, trim(implode(' ', array_filter([$address->city, $address->postal_code]))), $address->governorate];

        $country = $address->country;
        $lines[] = $ar ? $country->name_ar : $country->name;

        return implode($multiline ? "\n" : ($ar ? '، ' : ', '), array_values(array_filter($lines, fn (?string $l): bool => $l !== null && $l !== '')));
    }

    private function validate(AddressData $data): void
    {
        $code = (string) $data->get('country_code');
        $errors = [];

        if (! $this->countries->isActive($code)) {
            $errors['country_code'] = [__('validation.exists', ['attribute' => 'country'])];
        }

        $required = self::REQUIRED[$code] ?? null;
        if ($required !== null) {
            foreach ($required as $field) {
                if ($data->get($field) === null) {
                    $errors[$field] = [__('validation.required', ['attribute' => $field])];
                }
            }
        } elseif ($data->get('line1') === null && $data->get('city') === null) {
            $errors['line1'] = [__('validation.required', ['attribute' => 'address line 1'])];
        }

        if ($errors !== []) {
            throw new InvalidAddressException(context: ['errors' => $errors]);
        }
    }
}
