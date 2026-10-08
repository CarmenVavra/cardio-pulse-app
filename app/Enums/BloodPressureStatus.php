<?php

namespace App\Enums;

/**
 * Ampel-Klassifizierung – identisch in Patienten-App und Krankenhaus.
 *
 * Auswertungsreihenfolge: Rot → Blau (zu niedrig) → Gelb-Orange → Grün.
 * Hinweis: Der Bereich 130–139 / 85–89 ist im Konzept nicht definiert und wird
 * gemäß Design-Handoff als Gelb-Orange gewertet (mit medizinischer Leitung abstimmen).
 */
enum BloodPressureStatus: string
{
    // Reihenfolge der Fälle = Triage-Reihenfolge (Zähler auf dem Board).
    case Red = 'red';
    case Amber = 'amber';
    case Blue = 'blue';
    case Green = 'green';

    public static function classify(int $systolic, int $diastolic): self
    {
        return match (true) {
            $systolic >= 180 || $diastolic >= 120 => self::Red,
            $systolic < 90 || $diastolic < 60 => self::Blue,
            $systolic >= 130 || $diastolic >= 85 => self::Amber,
            default => self::Green,
        };
    }

    /**
     * Sortierreihenfolge der Krankenhauslisten: Rot → Gelb-Orange → Blau → Grün.
     * Zu niedrige Werte stehen vor normalen, damit Hypotonie nicht übersehen wird
     * (abweichend vom Konzept, das Blau zuletzt nennt).
     */
    public function sortOrder(): int
    {
        return match ($this) {
            self::Red => 0,
            self::Amber => 1,
            self::Blue => 2,
            self::Green => 3,
        };
    }

    /**
     * Liefert den dringlicheren der beiden Status (für Tages-/Monatsfarben).
     */
    public static function worst(?self $a, ?self $b): ?self
    {
        if ($a === null) {
            return $b;
        }
        if ($b === null) {
            return $a;
        }

        return $a->sortOrder() <= $b->sortOrder() ? $a : $b;
    }

    public function label(): string
    {
        return match ($this) {
            self::Red => 'Gefährlich hoch',
            self::Amber => 'Zu hoch',
            self::Green => 'Normal',
            self::Blue => 'Zu niedrig',
        };
    }

    public function tagLabel(): string
    {
        return match ($this) {
            self::Red => 'Gefährlich',
            default => $this->label(),
        };
    }

    public function colorName(): string
    {
        return match ($this) {
            self::Red => 'Rot',
            self::Amber => 'Gelb-Orange',
            self::Green => 'Grün',
            self::Blue => 'Blau',
        };
    }

    /**
     * Füllfarbe (Ampel). Blau = „Blue Ice“ aus dem Projektkonzept, leicht abgedunkelt
     * (#0288D1 → #0277BD), damit weiße Schrift darauf WCAG-AA-Kontrast erreicht.
     */
    public function color(): string
    {
        return match ($this) {
            self::Red => '#D32F2F',
            self::Amber => '#ED6C02',
            self::Green => '#2E7D32',
            self::Blue => '#0277BD',
        };
    }

    /**
     * Textfarbe für Werte auf hellem Grund (AA-Kontrast).
     */
    public function textColor(): string
    {
        return match ($this) {
            self::Red => '#B71C1C',
            self::Amber => '#9A4600',
            self::Green => '#1F5A23',
            self::Blue => '#01579B',
        };
    }

    /**
     * HL7 v3 ObservationInterpretation-Code für FHIR.
     */
    public function fhirInterpretation(): string
    {
        return match ($this) {
            self::Red => 'HH',
            self::Amber => 'H',
            self::Green => 'N',
            self::Blue => 'L',
        };
    }

    public function fhirInterpretationDisplay(): string
    {
        return match ($this) {
            self::Red => 'Critical high',
            self::Amber => 'High',
            self::Green => 'Normal',
            self::Blue => 'Low',
        };
    }

    /**
     * Handlungsempfehlung für den Patienten (Ergebnis-Screen).
     */
    public function advice(): string
    {
        return match ($this) {
            self::Red => 'Bei Brustschmerz, Atemnot, Sprach- oder Sehstörungen rufen Sie sofort den Notruf.',
            self::Amber => 'Leicht erhöht. Ruhen Sie sich 5 Minuten aus und messen Sie dann erneut.',
            self::Green => 'Ihr Blutdruck liegt im Normalbereich. Weiter so – messen Sie wie gewohnt.',
            self::Blue => 'Ihr Blutdruck ist niedrig. Setzen oder legen Sie sich hin und trinken Sie ein Glas Wasser. Bei Bewusstseinsstörung sofort 112 wählen.',
        };
    }

    /**
     * Position der Markierung auf der 4-teiligen Skala (0–100 %).
     * Segmente: Zu niedrig (1fr) · Normal (1.4fr) · Zu hoch (1.4fr) · Gefährlich (1fr).
     */
    public function scalePosition(int $systolic): float
    {
        // [Segmentbeginn, Segmentbreite, Untergrenze mmHg, Spannweite mmHg]
        [$start, $width, $min, $span] = match ($this) {
            self::Blue => [0.0, 1.0, 60, 30],
            self::Green => [1.0, 1.4, 90, 40],
            self::Amber => [2.4, 1.4, 130, 50],
            self::Red => [3.8, 1.0, 180, 50],
        };

        $fraction = ($systolic - $min) / $span;
        $fraction = max(0.08, min(0.92, $fraction));

        return round(($start + $fraction * $width) / 4.8 * 100, 1);
    }
}
