<?php

namespace Tests;

use App\Models\Measurement;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Dr. Miriam Weber – wie in den Demo-Daten Admin.
     */
    protected function staff(): User
    {
        return User::factory()->admin()->create(['username' => 'm.weber', 'name' => 'Miriam Weber']);
    }

    protected function patient(array $attributes = []): Patient
    {
        return Patient::factory()->create($attributes);
    }

    protected function measurement(Patient $patient, int $systolic, int $diastolic, array $attributes = []): Measurement
    {
        return Measurement::factory()
            ->for($patient)
            ->reading($systolic, $diastolic)
            ->create($attributes);
    }
}
