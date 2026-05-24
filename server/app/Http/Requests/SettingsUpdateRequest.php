<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'between:1,65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            // Plaintext password — blank means "leave existing value untouched".
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', Rule::in(['tls', 'ssl', 'none'])],
            'mail_from_address' => ['nullable', 'email:rfc', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],

            'alert_recipient' => ['nullable', 'email:rfc', 'max:255'],

            'disk_alert_percent' => ['required', 'integer', 'between:1,100'],
            'offline_threshold_seconds' => ['required', 'integer', 'between:30,86400'],
            'alert_throttle_minutes' => ['required', 'integer', 'between:1,1440'],
        ];
    }

    /**
     * Normalize "none" encryption to empty string (Laravel/Symfony Mailer expects null).
     *
     * @return array<string, mixed>
     */
    public function settingsPayload(): array
    {
        $data = $this->validated();
        $data['mail_encryption'] = $data['mail_encryption'] === 'none' ? '' : $data['mail_encryption'];

        return $data;
    }
}
