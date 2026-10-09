<?php

namespace App\Http\Requests;

use App\Enums\CallStatus;
use App\Models\Call;
use App\Models\CallSignal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Nachricht des WebRTC-Verbindungsaufbaus – nur Gesprächsteilnehmer, nur während des Gesprächs.
 */
class StoreCallSignalRequest extends FormRequest
{
    /** Ein Angebot mit Video ist meist 3–8 KB groß. */
    private const MAX_PAYLOAD_BYTES = 20000;

    public function authorize(): bool
    {
        $call = $this->route('call');

        return $call instanceof Call && (bool) $this->user()?->can('participate', $call);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(CallSignal::TYPES)],
            'payload' => ['present', 'array'],
            'payload.for' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $call = $this->route('call');
                if ($call instanceof Call && $call->status !== CallStatus::Active) {
                    $validator->errors()->add('type', 'Das Gespräch ist nicht aktiv.');
                }

                if (strlen((string) json_encode($this->input('payload'))) > self::MAX_PAYLOAD_BYTES) {
                    $validator->errors()->add('payload', 'Die Nachricht ist zu groß.');
                }
            },
        ];
    }

    /**
     * Die vollständige Nachricht (Angebot, Antwort oder Kandidat). validated() lieferte hier
     * nur die einzeln geprüften Unterfelder („for“) und verlöre das eigentliche SDP – die Größe
     * und der Typ sind oben bereits geprüft.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $payload = $this->input('payload');

        return is_array($payload) ? $payload : [];
    }
}
