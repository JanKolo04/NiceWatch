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
            // mail_host: deny private / loopback / link-local IPs to prevent SSRF
            // (operator can otherwise have the container open SMTP connections
            // to internal services and leak banner info via error messages).
            // Allowing 'mailpit' literal so the dev profile still works.
            'mail_host' => ['nullable', 'string', 'max:255', $this->mailHostRule()],
            // Allowlist common SMTP submission ports.
            'mail_port' => ['nullable', 'integer', Rule::in([25, 465, 587, 2525])],
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
     * Block SSRF-attractive hosts: loopback, private RFC1918, link-local,
     * cloud metadata IPs. Public FQDNs and the 'mailpit' container hostname pass.
     */
    private function mailHostRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }
            $host = strtolower((string) $value);

            // Allow the in-cluster dev mail catcher.
            if ($host === 'mailpit') {
                return;
            }

            // Reject obvious loopback aliases.
            $forbiddenNames = ['localhost', 'localhost.localdomain', 'ip6-localhost', 'ip6-loopback', 'broadcasthost'];
            if (in_array($host, $forbiddenNames, true)) {
                $fail('Adres SMTP nie może wskazywać na lokalny host.');

                return;
            }

            // If it parses as an IP, reject private/loopback/link-local/multicast/metadata.
            if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
                if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                    $fail('Adres SMTP nie może być adresem prywatnym, loopback ani link-local.');
                }
            }
        };
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
