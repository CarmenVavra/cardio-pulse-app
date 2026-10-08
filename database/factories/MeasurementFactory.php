<?php

namespace Database\Factories;

use App\Enums\BloodPressureStatus;
use App\Models\Measurement;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Measurement>
 */
class MeasurementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $systolic = fake()->numberBetween(110, 160);
        $diastolic = fake()->numberBetween(65, 99);

        return [
            'patient_id' => Patient::factory(),
            'systolic' => $systolic,
            'diastolic' => $diastolic,
            'pulse' => fake()->numberBetween(55, 100),
            'status' => BloodPressureStatus::classify($systolic, $diastolic),
            'method' => 'manual',
            'symptoms' => [],
            'measured_at' => now(),
        ];
    }

    /**
     * Fester Messwert inkl. passender Ampel-Klassifizierung.
     */
    public function reading(int $systolic, int $diastolic): static
    {
        return $this->state(fn (array $attributes) => [
            'systolic' => $systolic,
            'diastolic' => $diastolic,
            'status' => BloodPressureStatus::classify($systolic, $diastolic),
        ]);
    }
}
