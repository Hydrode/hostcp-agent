<?php

declare(strict_types=1);

namespace HostCP\Agent\Handlers;

use HostCP\Agent\Request;
use HostCP\Agent\Response;

final class LogHandler extends AbstractHandler
{
    public function get(Request $req, Response $res, array $params): void
    {
        if (!$this->cfg->featureWeb) {
            $res->json(403, ['error' => 'web feature disabled']);
            return;
        }

        $domain = $this->normalizeDomain((string) ($params['domain'] ?? ''));
        if (!$this->isValidDomain($domain)) {
            $res->json(400, ['error' => 'invalid domain']);
            return;
        }

        $type = (string) ($req->query('type', 'access'));
        if (!in_array($type, ['access', 'error'], true)) {
            $res->json(400, ['error' => 'type must be access or error']);
            return;
        }

        $logFile = match ($type) {
            'access' => "/var/log/nginx/{$domain}.access.log",
            'error'  => "/var/log/nginx/{$domain}.error.log",
        };

        if (!is_file($logFile)) {
            $res->json(404, ['error' => 'log not found']);
            return;
        }

        $lines = (int) ($req->query('lines', '50'));
        $lines = min(max($lines, 1), 500);

        $content = trim((string) (file_get_contents($logFile) ?: ''));
        $logLines = array_slice(preg_split('/\r?\n/', $content) ?: [], -$lines);

        $res->json(200, ['type' => $type, 'lines' => count($logLines), 'logs' => implode("\n", $logLines)]);
    }
}
