<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'doctor_id' => null,
            'patient_number' => 'CP-'.fake()->unique()->numberBetween(10000, 99999),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'birth_date' => fake()->dateTimeBetween('-85 years', '-40 years'),
            'street' => fake()->streetAddress(),
            'postal_code' => fake()->postcode(),
            'city' => fake()->city(),
            'phone' => fake()->phoneNumber(),
            'diagnosis' => 'Arterielle Hypertonie',
            'gp_name' => 'Dr. '.fake()->lastName(),
        ];
    }

    /**
     * Patient ohne App-Zugang.
     */
    public function withoutUser(): static
    {
        return $this->state(fn (array $attributes) => ['user_id' => null]);
    }
}
