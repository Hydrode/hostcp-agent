<?php

declare(strict_types=1);

namespace HostCP\Agent\Handlers;

use HostCP\Agent\Request;
use HostCP\Agent\Response;

final class HealthHandler extends AbstractHandler
{
    public function health(Request $req, Response $res, array $params): void
    {
        $res->json(200, ['status' => 'ok']);
    }

    public function monitor(Request $req, Response $res, array $params): void
    {
        if (!is_file('/proc/loadavg') || !is_file('/proc/meminfo')) {
            $res->json(500, ['error' => 'monitor: not supported on this platform']);
            return;
        }

        $loadRaw   = (string) (file_get_contents('/proc/loadavg') ?: '');
        $loadParts = preg_split('/\s+/', trim($loadRaw)) ?: [];
        $load1     = isset($loadParts[0]) ? (float) $loadParts[0] : 0.0;
        $load5     = isset($loadParts[1]) ? (float) $loadParts[1] : 0.0;

        $mem    = [];
        $memRaw = (string) (file_get_contents('/proc/meminfo') ?: '');
        foreach (preg_split('/\r?\n/', $memRaw) ?: [] as $line) {
            if (preg_match('/^(\w+):\s+(\d+)/', $line, $m) === 1) {
                $mem[$m[1]] = (int) $m[2];
            }
        }

        $ramTotal     = (int) ($mem['MemTotal'] ?? 0);
        $ramAvailable = (int) ($mem['MemAvailable'] ?? 0);
        $ramUsed      = max(0, $ramTotal - $ramAvailable);
        $diskTotal    = (int) (disk_total_space('/') ?: 0);
        $diskFree     = (int) (disk_free_space('/') ?: 0);
        $diskUsed     = max(0, $diskTotal - $diskFree);

        $res->json(200, [
            'load_avg_1'   => $load1,
            'load_avg_5'   => $load5,
            'ram_used_mb'  => (int) floor($ramUsed / 1024),
            'ram_total_mb' => (int) floor($ramTotal / 1024),
            'disk_used_gb' => (int) floor($diskUsed / 1024 / 1024 / 1024),
            'disk_total_gb'=> (int) floor($diskTotal / 1024 / 1024 / 1024),
        ]);
    }
}
