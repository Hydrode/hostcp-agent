<?php

declare(strict_types=1);

namespace HostCP\Agent\Handlers;

use HostCP\Agent\Request;
use HostCP\Agent\Response;

final class PhpHandler extends AbstractHandler
{
    public function versions(Request $req, Response $res, array $params): void
    {
        $res->json(200, ['versions' => $this->listInstalledPhpVersions()]);
    }

    public function switchVersion(Request $req, Response $res, array $params): void
    {
        if (!$this->cfg->featureWeb) {
            $res->json(403, ['error' => 'web feature disabled']);
            return;
        }

        $body       = $req->body();
        $domain     = $this->normalizeDomain((string) ($body['domain'] ?? ''));
        $newVersion = trim((string) ($body['php_version'] ?? ''));

        if (!$this->isValidDomain($domain) || $newVersion === '') {
            $res->json(400, ['error' => 'domain and php_version required']);
            return;
        }

        $safe           = $this->poolName($domain);
        $currentVersion = '';
        foreach ($this->listInstalledPhpVersions() as $ver) {
            if (is_file("/etc/php/{$ver}/fpm/pool.d/{$safe}.conf")) {
                $currentVersion = $ver;
                break;
            }
        }

        $vhostPath = "/etc/nginx/sites-available/{$domain}.conf";
        if (!is_file($vhostPath)) {
            $res->json(404, ['error' => 'vhost not found']);
            return;
        }

        $vhost = (string) (file_get_contents($vhostPath) ?: '');
        if ($currentVersion !== '') {
            $vhost = str_replace(
                "php{$currentVersion}-fpm-{$safe}.sock",
                "php{$newVersion}-fpm-{$safe}.sock",
                $vhost
            );
        } else {
            $vhost = (string) (preg_replace(
                '/php\d+\.\d+-fpm-' . preg_quote($safe, '/') . '\.sock/',
                "php{$newVersion}-fpm-{$safe}.sock",
                $vhost
            ) ?? $vhost);
        }

        if (file_put_contents($vhostPath, $vhost) === false) {
            $res->json(500, ['error' => 'unable to write vhost']);
            return;
        }

        if ($currentVersion !== '' && $currentVersion !== $newVersion) {
            @unlink("/etc/php/{$currentVersion}/fpm/pool.d/{$safe}.conf");
            $this->run(['systemctl', 'reload', "php{$currentVersion}-fpm"]);
        }

        if (!$this->writePool($domain, $newVersion, $res)) {
            return;
        }

        $this->run(['systemctl', 'reload', "php{$newVersion}-fpm"]);

        [$out, $ok] = $this->run(['nginx', '-t']);
        if (!$ok) {
            $res->json(500, ['error' => "nginx config test failed after php switch: {$out}"]);
            return;
        }

        $this->run(['systemctl', 'reload', 'nginx']);
        $res->json(200, ['status' => 'switched', 'php_version' => $newVersion]);
    }
}
