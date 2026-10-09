<?php

namespace App\Support;

use App\Models\Alarm;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Call;
use App\Models\Measurement;
use App\Models\Medication;
use App\Models\Message;
use App\Models\MonthlyReport;
use App\Models\Patient;
use App\Models\User;

/**
 * Lesbare Darstellung des Prüfprotokolls: Bereich, Aktion, Betroffener, Details.
 */
class AuditLogFormatter
{
    /**
     * Bereiche für den Filter (Präfix des Aktionscodes).
     */
    public const GROUPS = [
        'auth' => 'Anmeldung',
        'screen' => 'Bildschirmsperre',
        'account' => 'Eigenes Konto',
        'doctor' => 'Ärzte',
        'patient' => 'Patienten',
        'measurement' => 'Messungen',
        'alarm' => 'Alarme',
        'call' => 'Anrufe',
        'appointment' => 'Termine',
        'message' => 'Nachrichten',
        'medication' => 'Medikation',
        'monthly_report' => 'Monatsberichte',
        'export' => 'Export',
        'audit' => 'Protokoll',
    ];

    private const ACTIONS = [
        'auth.login' => 'Angemeldet',
        'auth.logout' => 'Abgemeldet',
        'auth.failed' => 'Anmeldung fehlgeschlagen',
        'auth.locked' => 'Anmeldung gesperrt (zu viele Fehlversuche)',
        'auth.two_factor_failed' => 'Falscher Zwei-Faktor-Code',
        'auth.recovery_code_used' => 'Wiederherstellungscode verwendet',
        'screen.locked' => 'Bildschirm gesperrt',
        'screen.unlocked' => 'Bildschirm entsperrt',
        'screen.unlock_failed' => 'Falsche PIN',
        'screen.unlock_locked_out' => 'Abgemeldet nach zu vielen falschen PINs',
        'account.password_changed' => 'Passwort geändert',
        'account.password_reset_requested' => 'Link zum Zurücksetzen angefordert',
        'account.password_reset' => 'Passwort per E-Mail-Link zurückgesetzt',
        'account.pin_changed' => 'PIN geändert',
        'account.two_factor_enabled' => 'Zwei-Faktor-Anmeldung eingerichtet',
        'account.two_factor_disabled' => 'Zwei-Faktor-Anmeldung abgeschaltet',
        'account.recovery_codes_regenerated' => 'Neue Wiederherstellungscodes',
        'doctor.created' => 'Arzt angelegt',
        'doctor.updated' => 'Arzt geändert',
        'doctor.deleted' => 'Arzt gelöscht',
        'doctor.two_factor_reset' => 'Zwei-Faktor-Anmeldung zurückgesetzt',
        'patient.created' => 'Patient angelegt',
        'patient.updated' => 'Patient geändert',
        'patient.deleted' => 'Patient gelöscht',
        'measurement.uploaded' => 'Messung übertragen',
        'measurement.symptom_free_confirmed' => 'Beschwerdefreiheit bestätigt',
        'alarm.acknowledged' => 'Alarm quittiert',
        'call.started' => 'Anruf gestartet',
        'call.answered' => 'Anruf angenommen',
        'call.declined' => 'Anruf abgelehnt',
        'call.ended' => 'Anruf beendet',
        'call.note_saved' => 'Gesprächsnotiz gespeichert',
        'appointment.scheduled' => 'Videosprechstunde vereinbart',
        'appointment.cancelled' => 'Termin abgesagt',
        'appointment.started' => 'Videosprechstunde gestartet',
        'appointment.reminded' => 'Terminerinnerung per E-Mail gesendet',
        'message.sent' => 'Nachricht an Patient gesendet',
        'message.received' => 'Nachricht vom Patienten',
        'medication.created' => 'Medikament erfasst',
        'medication.updated' => 'Medikament geändert',
        'medication.deleted' => 'Medikament gelöscht',
        'monthly_report.sent' => 'Monatsbericht gesendet',
        'monthly_report.resent' => 'Monatsbericht erneut gesendet',
        'export.fhir' => 'FHIR-Export heruntergeladen',
        'export.pdf' => 'Bericht gedruckt',
        'audit.exported' => 'Protokoll exportiert',
    ];

    private const KEYS = [
        'username' => 'Benutzerkennung',
        'identifier' => 'Kennung',
        'department' => 'Abteilung',
        'sound' => 'Signalton',
        'two_factor' => 'Zwei-Faktor',
        'fields' => 'Geänderte Felder',
        'patients_reassigned' => 'Patienten übergeben',
        'replacement_id' => 'Vertretung (ID)',
        'remaining' => 'Verbleibend',
        'via' => 'Über',
        'patient_number' => 'Patientennummer',
        'is_admin' => 'Admin',
        'measure' => 'Maßnahme',
        'from' => 'Von',
        'to' => 'Bis',
        'group' => 'Bereich',
        'count' => 'Einträge',
        'before' => 'Vorher',
        'after' => 'Nachher',
        'code' => 'Code',
        'direction' => 'Richtung',
        'duration_seconds' => 'Dauer (s)',
        'email' => 'E-Mail',
        'fhir_interpretation' => 'FHIR-Bewertung',
        'locked' => 'Gesperrt',
        'measurement_id' => 'Messung (ID)',
        'measurements' => 'Messungen',
        'month' => 'Monat',
        'note' => 'Notiz',
        'open_alarms' => 'Offene Alarme',
        'patient_id' => 'Patient (ID)',
        'reading' => 'Messwert',
        'reason' => 'Grund',
        'status' => 'Status',
        'app' => 'Zugang',
        'starts_at' => 'Termin',
        'minutes' => 'Dauer (Min)',
        'by_patient' => 'Vom Patienten',
    ];

    /**
     * Gespeicherte Codes in Detailwerten, z. B. app: patient → Zugang: Patienten-App.
     */
    private const VALUES = [
        'app' => ['patient' => 'Patienten-App'],
        'via' => ['console' => 'Kommandozeile (Server)'],
        'direction' => ['to_patient' => 'Arzt → Patient', 'to_clinic' => 'Patient → Klinik'],
        'status' => [
            'red' => 'Rot',
            'amber' => 'Gelb-Orange',
            'blue' => 'Blau',
            'green' => 'Grün',
        ],
    ];

    private const FIELDS = [
        'title' => 'Titel',
        'name' => 'Name',
        'username' => 'Benutzerkennung',
        'email' => 'E-Mail',
        'phone' => 'Telefon',
        'password' => 'Passwort',
        'pin' => 'PIN',
        'is_admin' => 'Admin-Rechte',
        'available_until' => 'Erreichbar bis',
        'first_name' => 'Vorname',
        'last_name' => 'Nachname',
        'birth_date' => 'Geburtsdatum',
        'street' => 'Straße',
        'postal_code' => 'PLZ',
        'city' => 'Ort',
        'diagnosis' => 'Diagnose',
        'gp_name' => 'Hausarzt',
        'doctor_id' => 'Behandelnder Arzt',
        'dose' => 'Dosis',
        'dosage' => 'Dosis',
        'schedule' => 'Einnahme',
    ];

    public static function groupOf(string $action): string
    {
        return strstr($action, '.', true) ?: $action;
    }

    public function group(AuditLog $log): string
    {
        return self::GROUPS[self::groupOf($log->action)] ?? self::groupOf($log->action);
    }

    public function action(AuditLog $log): string
    {
        return self::ACTIONS[$log->action] ?? $log->action;
    }

    /**
     * Wer hat gehandelt – Arzt, Patient oder das System (z. B. fehlgeschlagene Anmeldung).
     */
    public function actor(AuditLog $log): string
    {
        $user = $log->user;
        if ($user === null) {
            return '—';
        }

        $name = $user->isStaff() ? $user->displayName() : $user->name.' (Patient)';

        return $user->trashed() ? $name.' (gelöscht)' : $name;
    }

    /**
     * Betroffener Datensatz, z. B. „Josef Brandner (P-1001)“ oder „Messung 165/102 · Josef Brandner“.
     */
    public function subject(AuditLog $log): ?string
    {
        $subject = $log->auditable;

        return match (true) {
            $subject instanceof Patient => $this->patient($subject),
            $subject instanceof User => $subject->isStaff() ? $subject->displayName() : $subject->name,
            $subject instanceof Measurement => 'Messung '.$subject->reading().' · '.$this->patient($subject->patient),
            $subject instanceof Alarm => 'Alarm · '.$this->patient($subject->patient),
            $subject instanceof Call => 'Anruf · '.$this->patient($subject->patient),
            $subject instanceof Appointment => 'Termin '.$subject->starts_at->format('d.m.Y H:i').' · '.$this->patient($subject->patient),
            $subject instanceof Message => 'Nachricht · '.$this->patient($subject->patient),
            $subject instanceof Medication => $subject->name.' · '.$this->patient($subject->patient),
            $subject instanceof MonthlyReport => 'Monatsbericht '.Format::monthName($subject->month).' '.$subject->month->year.' · '.$this->patient($subject->patient),
            default => null,
        };
    }

    public function details(AuditLog $log): string
    {
        $parts = [];
        foreach ($log->details ?? [] as $key => $value) {
            if ($value === null || $value === [] || $value === '') {
                continue;
            }

            $parts[] = (self::KEYS[$key] ?? $key).': '.$this->value((string) $key, $value);
        }

        return implode(' · ', $parts);
    }

    private function value(string $key, mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'ja' : 'nein';
        }

        if (is_string($value) && isset(self::VALUES[$key][$value])) {
            return self::VALUES[$key][$value];
        }

        if ($key === 'group' && is_string($value)) {
            return self::GROUPS[$value] ?? $value;
        }

        if ($key === 'department' && is_string($value)) {
            return config('cardiopulse.departments')[$value] ?? $value;
        }

        if (is_array($value) && array_is_list($value)) {
            return implode(', ', array_map(fn ($item) => is_scalar($item) ? (self::FIELDS[(string) $item] ?? (string) $item) : (string) json_encode($item), $value));
        }

        // z. B. Vorher/Nachher einer Medikation: „Name: Ramipril, Dosis: 5 mg“
        if (is_array($value)) {
            $pairs = [];
            foreach ($value as $field => $item) {
                $pairs[] = (self::FIELDS[(string) $field] ?? (string) $field).': '.(is_scalar($item) || $item === null ? (string) $item : (string) json_encode($item));
            }

            return implode(', ', $pairs);
        }

        return is_scalar($value) ? (string) $value : (string) json_encode($value);
    }

    private function patient(?Patient $patient): string
    {
        if ($patient === null) {
            return 'Patient unbekannt';
        }

        $name = $patient->fullName().' ('.$patient->patient_number.')';

        return $patient->trashed() ? $name.' – gelöscht' : $name;
    }
}
