<?php

declare(strict_types=1);

namespace HostCP\Agent\Handlers;

use HostCP\Agent\Request;
use HostCP\Agent\Response;

final class VhostHandler extends AbstractHandler
{
    public function show(Request $req, Response $res, array $params): void
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

        $vhostPath = "/etc/nginx/sites-available/{$domain}.conf";
        if (!is_file($vhostPath)) {
            $res->json(404, ['error' => 'vhost not found']);
            return;
        }

        $content = (string) file_get_contents($vhostPath);
        $res->json(200, ['domain' => $domain, 'content' => $content]);
    }

    public function update(Request $req, Response $res, array $params): void
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

        $body = $req->body();
        $content = (string) ($body['content'] ?? '');
        if ($content === '') {
            $res->json(400, ['error' => 'content required']);
            return;
        }

        $vhostPath = "/etc/nginx/sites-available/{$domain}.conf";
        if (!is_file($vhostPath)) {
            $res->json(404, ['error' => 'vhost not found']);
            return;
        }

        if (file_put_contents($vhostPath, $content) === false) {
            $res->json(500, ['error' => 'unable to update vhost']);
            return;
        }

        if ($this->runCommand('nginx -t 2>&1', $output) !== 0) {
            $res->json(400, ['error' => 'invalid nginx config', 'output' => $output]);
            return;
        }

        if ($this->runCommand('systemctl reload nginx 2>&1', $output) !== 0) {
            $res->json(500, ['error' => 'unable to reload nginx', 'output' => $output]);
            return;
        }

        $res->json(200, ['success' => true, 'domain' => $domain]);
    }
}
