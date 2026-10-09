<?php

namespace App\Http\Requests;

use App\Services\PushService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Gerät für Push-Benachrichtigungen an- oder abmelden (PushSubscription aus dem Browser).
 */
class PushSubscriptionRequest extends FormRequest
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
        $endpoint = [
            'required', 'string', 'url', 'max:2000',
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! app(PushService::class)->isAllowedEndpoint((string) $value)) {
                    $fail('Dieser Push-Dienst wird nicht unterstützt.');
                }
            },
        ];

        if ($this->isMethod('DELETE')) {
            return ['endpoint' => ['required', 'string', 'max:2000']];
        }

        return [
            'endpoint' => $endpoint,
            'keys.p256dh' => ['required', 'string', 'max:200'],
            'keys.auth' => ['required', 'string', 'max:100'],
            'contentEncoding' => ['nullable', 'in:aes128gcm,aesgcm'],
        ];
    }
}
