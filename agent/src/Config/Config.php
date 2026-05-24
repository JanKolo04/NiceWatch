<?php

declare(strict_types=1);

namespace NiceWatch\Agent\Config;

use RuntimeException;

final class Config
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function __construct(private readonly array $data)
    {
    }

    public static function load(?string $path = null): self
    {
        $candidates = array_filter([
            $path,
            getenv('NICEWATCH_AGENT_CONFIG') ?: null,
            getcwd() . '/config.php',
            __DIR__ . '/../../config.php',
            '/etc/nicewatch/agent.php',
        ]);

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                $data = require $candidate;
                if (! is_array($data)) {
                    throw new RuntimeException("Config file {$candidate} must return an array.");
                }

                return new self($data);
            }
        }

        throw new RuntimeException(
            "No agent config found. Checked: " . implode(', ', $candidates) . ". " .
            "Copy config.php.dist to config.php and set server_url + api_token."
        );
    }

    public function serverUrl(): string
    {
        $url = $this->data['server_url'] ?? null;
        if (! is_string($url) || $url === '') {
            throw new RuntimeException('Config: server_url is required.');
        }

        return rtrim($url, '/');
    }

    public function apiToken(): string
    {
        $token = $this->data['api_token'] ?? null;
        if (! is_string($token) || $token === '' || $token === 'paste-bearer-token-here') {
            throw new RuntimeException('Config: api_token is required.');
        }

        return $token;
    }

    public function hostname(): string
    {
        $host = $this->data['hostname'] ?? null;
        if (is_string($host) && $host !== '') {
            return $host;
        }

        return gethostname() ?: 'unknown';
    }

    public function timeoutSeconds(): int
    {
        return (int) ($this->data['timeout_seconds'] ?? 10);
    }

    public function verifyTls(): bool
    {
        return (bool) ($this->data['verify_tls'] ?? true);
    }

    public function collectorEnabled(string $name): bool
    {
        return (bool) ($this->data['collectors'][$name] ?? false);
    }
}
