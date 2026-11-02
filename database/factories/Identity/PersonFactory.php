<?php

declare(strict_types=1);

namespace Database\Factories\Identity;

use App\Enums\Shared\Gender;
use App\Models\Identity\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    protected $model = Person::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement(Gender::cases());

        return [
            'first_name' => fake()->firstName($gender->value),
            'last_name' => fake()->lastName(),
            'gender' => $gender,
            'date_of_birth' => fake()->dateTimeBetween('-80 years', '-18 years')->format('Y-m-d'),
            'phone' => '+9733'.fake()->unique()->numerify('#######'),
            'email' => fake()->unique()->safeEmail(),
            'language' => 'en',
        ];
    }

    public function withNationalId(?string $nationalId = null): static
    {
        return $this->state(fn (): array => ['national_id' => $nationalId ?? fake()->unique()->numerify('#########')]);
    }
}
