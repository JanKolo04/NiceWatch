<?php

declare(strict_types=1);

namespace NiceWatch\Agent\Reporter;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use NiceWatch\Agent\Config\Config;
use RuntimeException;

final class Reporter
{
    private const AGENT_VERSION = '0.1.0';

    public function __construct(private readonly Config $config, private readonly ?Client $client = null)
    {
    }

    /**
     * @param  array<string, mixed>  $systemSnapshot
     * @return array<string, mixed>  Server response decoded as array.
     */
    public function sendCheckin(array $systemSnapshot): array
    {
        $client = $this->client ?? $this->makeClient();

        $payload = [
            'agent_version' => self::AGENT_VERSION,
            'collected_at' => date('c'),
            'system' => $systemSnapshot,
        ];

        try {
            $response = $client->post('/api/v1/checkin', [
                'json' => $payload,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->config->apiToken(),
                    'Accept' => 'application/json',
                ],
            ]);
        } catch (GuzzleException $e) {
            throw new RuntimeException("Checkin failed: {$e->getMessage()}", 0, $e);
        }

        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : ['raw' => $body];
    }

    /**
     * @return array<string, mixed>  Decoded /api/v1/config response.
     */
    public function fetchConfig(): array
    {
        $client = $this->client ?? $this->makeClient();

        try {
            $response = $client->get('/api/v1/config', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->config->apiToken(),
                    'Accept' => 'application/json',
                ],
            ]);
        } catch (GuzzleException $e) {
            throw new RuntimeException("Config fetch failed: {$e->getMessage()}", 0, $e);
        }

        $decoded = json_decode((string) $response->getBody(), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Config endpoint returned non-array response.');
        }

        return $decoded;
    }

    private function makeClient(): Client
    {
        return new Client([
            'base_uri' => $this->config->serverUrl(),
            'timeout' => $this->config->timeoutSeconds(),
            'verify' => $this->config->verifyTls(),
            'http_errors' => true,
        ]);
    }
}
