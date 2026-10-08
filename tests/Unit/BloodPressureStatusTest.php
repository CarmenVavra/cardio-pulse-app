<?php

namespace Tests\Unit;

use App\Enums\BloodPressureStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BloodPressureStatusTest extends TestCase
{
    /**
     * @return array<string, array{int, int, BloodPressureStatus}>
     */
    public static function readings(): array
    {
        return [
            'rot systolisch' => [180, 100, BloodPressureStatus::Red],
            'rot diastolisch' => [150, 120, BloodPressureStatus::Red],
            'rot vor weiß' => [185, 55, BloodPressureStatus::Red],
            'weiß systolisch' => [89, 70, BloodPressureStatus::White],
            'weiß diastolisch' => [110, 59, BloodPressureStatus::White],
            'gelb systolisch' => [130, 80, BloodPressureStatus::Amber],
            'gelb diastolisch' => [125, 85, BloodPressureStatus::Amber],
            'gelb knapp unter rot' => [179, 119, BloodPressureStatus::Amber],
            'grün' => [129, 84, BloodPressureStatus::Green],
            'grün untere Grenze' => [90, 60, BloodPressureStatus::Green],
        ];
    }

    #[DataProvider('readings')]
    public function test_classifies_readings_with_traffic_light(int $systolic, int $diastolic, BloodPressureStatus $expected): void
    {
        $this->assertSame($expected, BloodPressureStatus::classify($systolic, $diastolic));
    }

    public function test_worst_prefers_triage_order(): void
    {
        $this->assertSame(BloodPressureStatus::Red, BloodPressureStatus::worst(BloodPressureStatus::Green, BloodPressureStatus::Red));
        $this->assertSame(BloodPressureStatus::Amber, BloodPressureStatus::worst(BloodPressureStatus::Amber, BloodPressureStatus::White));
        $this->assertSame(BloodPressureStatus::Green, BloodPressureStatus::worst(null, BloodPressureStatus::Green));
    }

    public function test_fhir_interpretation_codes(): void
    {
        $this->assertSame('HH', BloodPressureStatus::Red->fhirInterpretation());
        $this->assertSame('H', BloodPressureStatus::Amber->fhirInterpretation());
        $this->assertSame('N', BloodPressureStatus::Green->fhirInterpretation());
        $this->assertSame('L', BloodPressureStatus::White->fhirInterpretation());
    }

    public function test_scale_marker_lies_within_its_segment(): void
    {
        $amber = BloodPressureStatus::Amber->scalePosition(136);

        $this->assertGreaterThan(2.4 / 4.8 * 100, $amber);
        $this->assertLessThan(3.8 / 4.8 * 100, $amber);
    }
}
