<?php

namespace App\Services;

use App\Models\Alarm;
use App\Models\AuditLog;
use App\Models\Call;
use App\Models\Measurement;
use App\Models\Medication;
use App\Models\Message;
use App\Models\MonthlyReport;
use App\Models\Patient;
use App\Models\User;
use App\Support\AuditLogFormatter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Prüfprotokoll durchsuchen und als CSV exportieren (nur Admins).
 */
class AuditLogService
{
    public const PER_PAGE = 50;

    public function __construct(private readonly AuditLogFormatter $formatter) {}

    /**
     * @param  array{from: ?string, to: ?string, group: ?string, user_id: ?int}  $filters
     * @return LengthAwarePaginator<int, array{time: string, group: string, action: string, actor: string, subject: ?string, details: string, ip: ?string}>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->query($filters)
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (AuditLog $log) => $this->row($log));
    }

    /**
     * @param  array{from: ?string, to: ?string, group: ?string, user_id: ?int}  $filters
     */
    public function export(array $filters): StreamedResponse
    {
        AuditLog::record('audit.exported', null, array_filter([
            'from' => $filters['from'],
            'to' => $filters['to'],
            'group' => $filters['group'],
        ]));

        $query = $this->query($filters);
        $filename = 'cardiopulse-protokoll-'.now()->format('Y-m-d_Hi').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            // UTF-8-BOM und Semikolon: Excel öffnet die Datei so direkt mit Umlauten.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Zeit', 'Bereich', 'Aktion', 'Benutzer', 'Betroffen', 'Details', 'IP-Adresse'], ';', '"', '');

            foreach ($query->lazyByIdDesc(500) as $log) {
                $row = $this->row($log);
                fputcsv($out, array_map($this->cell(...), [
                    $row['time'], $row['group'], $row['action'], $row['actor'], $row['subject'] ?? '', $row['details'], $row['ip'] ?? '',
                ]), ';', '"', '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  array{from: ?string, to: ?string, group: ?string, user_id: ?int}  $filters
     * @return Builder<AuditLog>
     */
    private function query(array $filters): Builder
    {
        $patient = ['patient' => fn ($query) => $query->withTrashed()];

        return AuditLog::query()
            ->with([
                'user',
                'auditable' => fn (MorphTo $morph) => $morph
                    ->constrain([
                        Patient::class => fn ($query) => $query->withTrashed(),
                        User::class => fn ($query) => $query->withTrashed(),
                    ])
                    ->morphWith([
                        Measurement::class => $patient,
                        Alarm::class => $patient,
                        Call::class => $patient,
                        Message::class => $patient,
                        Medication::class => $patient,
                        MonthlyReport::class => $patient,
                    ]),
            ])
            ->when($filters['from'], fn (Builder $query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'], fn (Builder $query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->when($filters['group'], fn (Builder $query, string $group) => $query->where('action', 'like', $group.'.%'))
            ->when($filters['user_id'], fn (Builder $query, int $userId) => $query->where('user_id', $userId));
    }

    /**
     * @return array{time: string, group: string, action: string, actor: string, subject: ?string, details: string, ip: ?string}
     */
    private function row(AuditLog $log): array
    {
        return [
            'time' => $log->created_at->format('d.m.Y H:i:s'),
            'group' => $this->formatter->group($log),
            'action' => $this->formatter->action($log),
            'actor' => $this->formatter->actor($log),
            'subject' => $this->formatter->subject($log),
            'details' => $this->formatter->details($log),
            'ip' => $log->ip_address,
        ];
    }

    /**
     * Schutz vor CSV-Injection: Zellen, die mit =, +, - oder @ beginnen, führt Excel sonst als Formel aus.
     */
    private function cell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
