<?php

namespace App\Http\Requests;

use App\Enums\SosResponse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Nach der Notfalltaste: Patient ruft selbst den Notruf an oder bittet das Krankenhaus darum.
 */
class SosResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->patient !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'response' => ['required', Rule::enum(SosResponse::class)],
        ];
    }

    public function sosResponse(): SosResponse
    {
        return SosResponse::from($this->validated('response'));
    }
}
