<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Arzt löschen: Bestätigung, Vertretung für zugewiesene Patienten, Schutzregeln.
 */
class DeleteDoctorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-doctors');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $doctor = $this->doctor();

        return [
            'confirm' => ['accepted'],
            'replacement_id' => [
                $doctor->patients()->exists() ? 'required' : 'nullable',
                'integer',
                Rule::exists('users', 'id')->where('role', UserRole::Staff->value)->whereNull('deleted_at'),
                Rule::notIn([$doctor->id]),
            ],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $doctor = $this->doctor();

                if ($doctor->is($this->user())) {
                    $validator->errors()->add('doctor', 'Sie können Ihr eigenes Konto nicht löschen.');
                }

                if (User::query()->where('role', UserRole::Staff)->count() <= 1) {
                    $validator->errors()->add('doctor', 'Der letzte Arzt kann nicht gelöscht werden.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirm.accepted' => 'Bitte bestätigen Sie das Löschen.',
            'replacement_id.required' => 'Bitte wählen Sie einen Arzt, der die Patienten übernimmt.',
            'replacement_id.not_in' => 'Die Vertretung muss ein anderer Arzt sein.',
            'replacement_id.exists' => 'Bitte wählen Sie einen aktiven Arzt als Vertretung.',
        ];
    }

    public function doctor(): User
    {
        $doctor = $this->route('doctor');

        abort_unless($doctor instanceof User, 404);

        return $doctor;
    }

    public function replacement(): ?User
    {
        $id = $this->validated('replacement_id');

        return $id ? User::query()->findOrFail($id) : null;
    }
}
