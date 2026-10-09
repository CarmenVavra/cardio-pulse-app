<?php

namespace App\Http\Requests;

use App\Support\AuditLogFormatter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filter für das Prüfprotokoll: Zeitraum, Bereich, Benutzer.
 */
class AuditLogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('view-audit-log');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'group' => ['nullable', Rule::in(array_keys(AuditLogFormatter::GROUPS))],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'from' => 'Von',
            'to' => 'Bis',
            'group' => 'Bereich',
            'user_id' => 'Benutzer',
        ];
    }

    /**
     * @return array{from: ?string, to: ?string, group: ?string, user_id: ?int}
     */
    public function filters(): array
    {
        $userId = $this->validated('user_id');

        return [
            'from' => $this->validated('from'),
            'to' => $this->validated('to'),
            'group' => $this->validated('group'),
            'user_id' => $userId !== null ? (int) $userId : null,
        ];
    }
}
