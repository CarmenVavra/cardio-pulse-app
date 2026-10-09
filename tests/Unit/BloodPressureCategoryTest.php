<?php

namespace Tests\Unit;

use App\Enums\BloodPressureCategory;
use App\Enums\BloodPressureStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Grenzwerte nach ESC/ESH (Österreich/Europa).
 */
class BloodPressureCategoryTest extends TestCase
{
    /**
     * @return array<string, array{int, int, BloodPressureCategory}>
     */
    public static function readings(): array
    {
        return [
            'normal' => [129, 84, BloodPressureCategory::Normal],
            'normal untere Grenze' => [90, 60, BloodPressureCategory::Normal],
            'hoch-normal systolisch' => [130, 80, BloodPressureCategory::HighNormal],
            'hoch-normal diastolisch' => [120, 85, BloodPressureCategory::HighNormal],
            'hoch-normal obere Grenze' => [139, 89, BloodPressureCategory::HighNormal],
            'Grad 1 systolisch' => [140, 80, BloodPressureCategory::Grade1],
            'Grad 1 diastolisch' => [125, 90, BloodPressureCategory::Grade1],
            'Grad 1 obere Grenze' => [159, 99, BloodPressureCategory::Grade1],
            'Grad 2 systolisch' => [160, 80, BloodPressureCategory::Grade2],
            'Grad 2 diastolisch' => [135, 100, BloodPressureCategory::Grade2],
            'Grad 2 obere Grenze' => [179, 109, BloodPressureCategory::Grade2],
            'Grad 3 systolisch' => [180, 90, BloodPressureCategory::Grade3],
            'Grad 3 diastolisch' => [150, 110, BloodPressureCategory::Grade3],
            'Grad 3 obere Grenze' => [180, 120, BloodPressureCategory::Grade3],
            'Krise systolisch' => [181, 100, BloodPressureCategory::Crisis],
            'Krise diastolisch' => [170, 121, BloodPressureCategory::Crisis],
            'der höhere Bereich zählt' => [145, 105, BloodPressureCategory::Grade2],
            'zu niedrig systolisch' => [89, 70, BloodPressureCategory::Low],
            'zu niedrig diastolisch' => [110, 59, BloodPressureCategory::Low],
            'Grad 3 vor zu niedrig' => [185, 55, BloodPressureCategory::Crisis],
        ];
    }

    #[DataProvider('readings')]
    public function test_classifies_by_esc_grades(int $systolic, int $diastolic, BloodPressureCategory $expected): void
    {
        $this->assertSame($expected, BloodPressureCategory::classify($systolic, $diastolic));
    }

    public function test_traffic_light_follows_the_grades(): void
    {
        $this->assertSame(BloodPressureStatus::Green, BloodPressureCategory::Normal->status());
        $this->assertSame(BloodPressureStatus::Amber, BloodPressureCategory::HighNormal->status());
        $this->assertSame(BloodPressureStatus::Amber, BloodPressureCategory::Grade1->status());
        $this->assertSame(BloodPressureStatus::Amber, BloodPressureCategory::Grade2->status());
        $this->assertSame(BloodPressureStatus::Red, BloodPressureCategory::Grade3->status());
        $this->assertSame(BloodPressureStatus::Red, BloodPressureCategory::Crisis->status());
        $this->assertSame(BloodPressureStatus::Blue, BloodPressureCategory::Low->status());
    }

    public function test_labels_follow_the_guideline(): void
    {
        $this->assertSame('Hoch-normal', BloodPressureCategory::HighNormal->label());
        $this->assertSame('Hypertonie Grad 1', BloodPressureCategory::Grade1->label());
        $this->assertSame('milder Hochdruck', BloodPressureCategory::Grade1->description());
        $this->assertSame('mäßiger Hochdruck', BloodPressureCategory::Grade2->description());
        $this->assertSame('schwerer Hochdruck', BloodPressureCategory::Grade3->description());
        $this->assertSame('Hypertensive Krise', BloodPressureCategory::Crisis->label());
    }
}
