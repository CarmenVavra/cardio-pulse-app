<?php

namespace App\Enums;

/**
 * Blutdruck-Einteilung nach den Leitlinien der European Society of Cardiology (ESC)
 * und der European Society of Hypertension (ESH), wie in Österreich und Europa üblich.
 *
 * Systolisch und diastolisch werden einzeln eingestuft; es zählt der höhere Bereich
 * („oder“). Zu niedrige Werte (Hypotonie) sind nicht Teil der ESC-Tabelle und folgen
 * der bisherigen Grenze < 90 / < 60 mmHg.
 *
 * Auswertungsreihenfolge: Krise → Grad 3 → zu niedrig → Grad 2 → Grad 1 → hoch-normal → normal.
 */
enum BloodPressureCategory: string
{
    case Crisis = 'crisis';
    case Grade3 = 'grade3';
    case Low = 'low';
    case Grade2 = 'grade2';
    case Grade1 = 'grade1';
    case HighNormal = 'high_normal';
    case Normal = 'normal';

    public static function classify(int $systolic, int $diastolic): self
    {
        return match (true) {
            $systolic > 180 || $diastolic > 120 => self::Crisis,
            $systolic >= 180 || $diastolic >= 110 => self::Grade3,
            $systolic < 90 || $diastolic < 60 => self::Low,
            $systolic >= 160 || $diastolic >= 100 => self::Grade2,
            $systolic >= 140 || $diastolic >= 90 => self::Grade1,
            $systolic >= 130 || $diastolic >= 85 => self::HighNormal,
            default => self::Normal,
        };
    }

    /**
     * Ampelfarbe: Grad 3 und Krise lösen den Alarm aus, hoch-normal bis Grad 2 sind
     * Gelb-Orange („beobachten“).
     */
    public function status(): BloodPressureStatus
    {
        return match ($this) {
            self::Crisis, self::Grade3 => BloodPressureStatus::Red,
            self::Low => BloodPressureStatus::Blue,
            self::Grade2, self::Grade1, self::HighNormal => BloodPressureStatus::Amber,
            self::Normal => BloodPressureStatus::Green,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Crisis => 'Hypertensive Krise',
            self::Grade3 => 'Hypertonie Grad 3',
            self::Low => 'Zu niedrig',
            self::Grade2 => 'Hypertonie Grad 2',
            self::Grade1 => 'Hypertonie Grad 1',
            self::HighNormal => 'Hoch-normal',
            self::Normal => 'Normal',
        };
    }

    /**
     * Erläuterung in Alltagssprache, z. B. „schwerer Hochdruck“.
     */
    public function description(): string
    {
        return match ($this) {
            self::Crisis => 'medizinischer Notfall möglich',
            self::Grade3 => 'schwerer Hochdruck',
            self::Low => 'Hypotonie',
            self::Grade2 => 'mäßiger Hochdruck',
            self::Grade1 => 'milder Hochdruck',
            self::HighNormal => 'Vorfeld des Bluthochdrucks',
            self::Normal => 'unter 130 / 85 mmHg',
        };
    }

    /**
     * Handlungsempfehlung für den Patienten (Ergebnis- bzw. Notfall-Screen).
     */
    public function advice(): string
    {
        $emergency = 'Notruf '.config('cardiopulse.emergency_number');
        $symptoms = 'starken Kopfschmerzen, Sehstörungen, Brustschmerzen, Atemnot oder Schwindel';

        return match ($this) {
            self::Crisis => 'Extrem hoher Wert. Bei '.$symptoms.' wählen Sie sofort den '.$emergency.'.',
            self::Grade3 => 'Stark erhöhter Wert. Bei '.$symptoms.' wählen Sie sofort den '.$emergency.'. Ihr Behandlungsteam wurde informiert.',
            self::Low => 'Ihr Blutdruck ist niedrig. Setzen oder legen Sie sich hin und trinken Sie ein Glas Wasser. Bei Bewusstseinsstörung sofort den '.$emergency.' wählen.',
            self::Grade2 => 'Deutlich erhöht. Ruhen Sie sich 5 Minuten aus und messen Sie dann erneut. Bleibt der Wert so hoch, melden Sie sich bei Ihrem Behandlungsteam.',
            self::Grade1 => 'Erhöht. Ruhen Sie sich 5 Minuten aus und messen Sie dann erneut. Eine einzelne Messung ist noch keine Diagnose.',
            self::HighNormal => 'Etwas über dem optimalen Bereich, aber noch kein Bluthochdruck. Messen Sie weiter regelmäßig.',
            self::Normal => 'Ihr Blutdruck liegt im Normalbereich. Weiter so – messen Sie wie gewohnt.',
        };
    }
}
