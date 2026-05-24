<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Database\QueryException;
use Throwable;

class SettingsRepository
{
    /** Keys persisted as encrypted strings. */
    public const ENCRYPTED_KEYS = ['mail_password'];

    /** Default values applied when a key has not been stored yet. */
    public const DEFAULTS = [
        'mail_host' => null,
        'mail_port' => '587',
        'mail_username' => null,
        'mail_password' => null,
        'mail_encryption' => 'tls',
        'mail_from_address' => null,
        'mail_from_name' => 'NiceWatch',

        'alert_recipient' => null,

        'disk_alert_percent' => '85',
        'offline_threshold_seconds' => '120',
        'alert_throttle_minutes' => '60',
    ];

    /** @var array<string, ?string>|null */
    private ?array $cache = null;

    public function get(string $key, mixed $default = null): ?string
    {
        $value = $this->all()[$key] ?? null;

        if ($value === null || $value === '') {
            $fallback = self::DEFAULTS[$key] ?? $default;

            return $fallback === null ? null : (string) $fallback;
        }

        return $value;
    }

    /**
     * @return array<string, ?string>
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $this->cache = [];

        // Resilient against missing table during early bootstrap (fresh install before migrate).
        try {
            $rows = Setting::query()->get();
        } catch (QueryException) {
            return $this->cache;
        }

        foreach ($rows as $row) {
            $this->cache[$row->key] = $row->decryptedValue();
        }

        return $this->cache;
    }

    /**
     * Persist a single key. Plaintext is encrypted automatically for keys in ENCRYPTED_KEYS.
     * Accepts string|int|float|bool|null — everything is normalized to a string before storage.
     */
    public function set(string $key, mixed $value): Setting
    {
        $normalized = $this->normalizeForStorage($value);
        $isEncrypted = in_array($key, self::ENCRYPTED_KEYS, true);

        $setting = Setting::query()->firstOrNew(['key' => $key]);

        if ($isEncrypted) {
            $setting->setSecretValue($normalized);
        } else {
            $setting->value = $normalized;
            $setting->is_encrypted = false;
        }
        $setting->save();

        $this->cache = null;

        return $setting;
    }

    /**
     * Persist many keys at once. Returns updated full cache.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, ?string>
     */
    public function setMany(array $values): array
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }

        return $this->all();
    }

    private function normalizeForStorage(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    public function flushCache(): void
    {
        $this->cache = null;
    }

    public function hasUsableMail(): bool
    {
        return $this->get('mail_host') !== null
            && $this->get('mail_from_address') !== null;
    }

    public function hasAlertRecipient(): bool
    {
        return $this->get('alert_recipient') !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function mailConfig(): array
    {
        $encryption = $this->get('mail_encryption');

        return [
            'host' => $this->get('mail_host'),
            'port' => (int) $this->get('mail_port'),
            'username' => $this->get('mail_username') ?: null,
            'password' => $this->get('mail_password') ?: null,
            'encryption' => $encryption === '' ? null : $encryption,
            'from_address' => $this->get('mail_from_address'),
            'from_name' => $this->get('mail_from_name') ?? 'NiceWatch',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function alertConfig(): array
    {
        return [
            'recipient' => $this->get('alert_recipient'),
            'disk_alert_percent' => (float) $this->get('disk_alert_percent'),
            'offline_threshold_seconds' => (int) $this->get('offline_threshold_seconds'),
            'alert_throttle_minutes' => (int) $this->get('alert_throttle_minutes'),
        ];
    }

    /**
     * Applies stored mail credentials to the Laravel runtime config. Safe to call multiple times.
     */
    public function applyToLaravelConfig(): void
    {
        if (! $this->hasUsableMail()) {
            return;
        }

        $mail = $this->mailConfig();

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $mail['host'],
            'mail.mailers.smtp.port' => $mail['port'],
            'mail.mailers.smtp.username' => $mail['username'],
            'mail.mailers.smtp.password' => $mail['password'],
            'mail.mailers.smtp.encryption' => $mail['encryption'],
            'mail.from.address' => $mail['from_address'],
            'mail.from.name' => $mail['from_name'],
        ]);
    }
}
