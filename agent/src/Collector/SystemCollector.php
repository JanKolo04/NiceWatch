<?php

declare(strict_types=1);

namespace NiceWatch\Agent\Collector;

use Symfony\Component\Process\Process;

final class SystemCollector
{
    /**
     * @return array<string, mixed>
     */
    public function collect(string $hostname): array
    {
        return [
            'hostname' => $hostname,
            'kernel' => $this->kernel(),
            'uptime_seconds' => $this->uptimeSeconds(),
            'cpu' => $this->cpu(),
            'memory' => $this->memory(),
            'disks' => $this->disks(),
            'network' => $this->network(),
        ];
    }

    private function kernel(): ?string
    {
        $sysname = php_uname('s');
        $release = php_uname('r');
        $value = trim($sysname . ' ' . $release);

        return $value !== '' ? $value : null;
    }

    private function uptimeSeconds(): ?int
    {
        $raw = @file_get_contents('/proc/uptime');
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        $parts = preg_split('/\s+/', trim($raw));

        return $parts !== false && isset($parts[0]) ? (int) $parts[0] : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function cpu(): array
    {
        $cores = $this->cpuCores();
        $usage = $this->cpuUsagePercent();
        $load = $this->loadAverage();

        return [
            'cores' => $cores,
            'usage_percent' => $usage,
            'load_1' => $load[0] ?? null,
            'load_5' => $load[1] ?? null,
            'load_15' => $load[2] ?? null,
        ];
    }

    private function cpuCores(): ?int
    {
        $cpuinfo = @file_get_contents('/proc/cpuinfo');
        if (! is_string($cpuinfo)) {
            return null;
        }

        return max(1, substr_count($cpuinfo, 'processor'));
    }

    /**
     * Calculates CPU usage by sampling /proc/stat twice with a 200 ms gap.
     */
    private function cpuUsagePercent(): ?float
    {
        $first = $this->readCpuTotals();
        if ($first === null) {
            return null;
        }
        usleep(200_000);
        $second = $this->readCpuTotals();
        if ($second === null) {
            return null;
        }

        $totalDelta = $second['total'] - $first['total'];
        $idleDelta = $second['idle'] - $first['idle'];
        if ($totalDelta <= 0) {
            return null;
        }

        return round(100.0 * (1.0 - $idleDelta / $totalDelta), 2);
    }

    /**
     * @return array{total: int, idle: int}|null
     */
    private function readCpuTotals(): ?array
    {
        $raw = @file_get_contents('/proc/stat');
        if (! is_string($raw)) {
            return null;
        }
        $line = strtok($raw, "\n");
        if (! is_string($line) || ! str_starts_with($line, 'cpu ')) {
            return null;
        }
        $parts = preg_split('/\s+/', trim($line));
        if ($parts === false || count($parts) < 5) {
            return null;
        }

        $values = array_map('intval', array_slice($parts, 1));
        $idle = ($values[3] ?? 0) + ($values[4] ?? 0);

        return ['total' => array_sum($values), 'idle' => $idle];
    }

    /**
     * @return array<int, float>|array{}
     */
    private function loadAverage(): array
    {
        $loadavg = @file_get_contents('/proc/loadavg');
        if (! is_string($loadavg) || $loadavg === '') {
            $load = function_exists('sys_getloadavg') ? sys_getloadavg() : false;

            return is_array($load) ? array_map('floatval', $load) : [];
        }
        $parts = preg_split('/\s+/', trim($loadavg));
        if ($parts === false) {
            return [];
        }

        return array_map('floatval', array_slice($parts, 0, 3));
    }

    /**
     * @return array<string, mixed>
     */
    private function memory(): array
    {
        $raw = @file_get_contents('/proc/meminfo');
        if (! is_string($raw)) {
            return [];
        }

        $values = [];
        foreach (explode("\n", $raw) as $line) {
            if (preg_match('/^(\w+):\s+(\d+)\s*kB$/', $line, $m) === 1) {
                $values[$m[1]] = (int) $m[2] * 1024;
            }
        }

        $total = $values['MemTotal'] ?? null;
        $available = $values['MemAvailable'] ?? null;
        $used = ($total !== null && $available !== null) ? $total - $available : null;

        return [
            'total_bytes' => $total,
            'available_bytes' => $available,
            'used_bytes' => $used,
            'used_percent' => ($total > 0 && $used !== null) ? round(100.0 * $used / $total, 2) : null,
            'swap_total_bytes' => $values['SwapTotal'] ?? null,
            'swap_used_bytes' => isset($values['SwapTotal'], $values['SwapFree'])
                ? $values['SwapTotal'] - $values['SwapFree']
                : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function disks(): array
    {
        $process = new Process(['df', '-P', '-B1', '-T', '--exclude-type=tmpfs', '--exclude-type=devtmpfs', '--exclude-type=squashfs']);
        $process->setTimeout(5.0);
        try {
            $process->run();
        } catch (\Throwable) {
            return [];
        }
        if (! $process->isSuccessful()) {
            return [];
        }

        $disks = [];
        $lines = preg_split('/\R/', trim($process->getOutput())) ?: [];
        foreach (array_slice($lines, 1) as $line) {
            $cols = preg_split('/\s+/', trim($line));
            if ($cols === false || count($cols) < 7) {
                continue;
            }
            [, $fs, $total, $used, $avail, , $mount] = $cols;
            $totalInt = (int) $total;
            $usedInt = (int) $used;
            $disks[] = [
                'mount' => $mount,
                'filesystem' => $fs,
                'total_bytes' => $totalInt,
                'used_bytes' => $usedInt,
                'available_bytes' => (int) $avail,
                'used_percent' => $totalInt > 0 ? round(100.0 * $usedInt / $totalInt, 2) : null,
            ];
        }

        return $disks;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function network(): array
    {
        $raw = @file_get_contents('/proc/net/dev');
        if (! is_string($raw)) {
            return [];
        }
        $lines = explode("\n", $raw);
        $ifaces = [];
        foreach (array_slice($lines, 2) as $line) {
            $line = trim($line);
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }
            [$iface, $rest] = explode(':', $line, 2);
            $iface = trim($iface);
            if ($iface === 'lo') {
                continue;
            }
            $cols = preg_split('/\s+/', trim($rest));
            if ($cols === false || count($cols) < 10) {
                continue;
            }
            $ifaces[] = [
                'iface' => $iface,
                'rx_bytes' => (int) $cols[0],
                'tx_bytes' => (int) $cols[8],
            ];
        }

        return $ifaces;
    }
}
