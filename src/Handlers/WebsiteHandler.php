<?php

declare(strict_types=1);

namespace HostCP\Agent\Handlers;

use HostCP\Agent\Request;
use HostCP\Agent\Response;

final class WebsiteHandler extends AbstractHandler
{
    public function create(Request $req, Response $res, array $params): void
    {
        if (!$this->cfg->featureWeb) {
            $res->json(403, ['error' => 'web feature disabled']);
            return;
        }

        $body       = $req->body();
        $domain     = $this->normalizeDomain((string) ($body['domain'] ?? ''));
        $phpVersion = trim((string) ($body['php_version'] ?? ''));

        if (!$this->isValidDomain($domain) || $phpVersion === '') {
            $res->json(400, ['error' => 'domain and php_version required']);
            return;
        }

        $docRoot = "/var/www/{$domain}/public_html";
        if (!is_dir($docRoot) && !mkdir($docRoot, 0755, true) && !is_dir($docRoot)) {
            $res->json(500, ['error' => 'unable to create document root']);
            return;
        }

        $vhostPath = "/etc/nginx/sites-available/{$domain}.conf";
        if (file_put_contents($vhostPath, $this->generateVhost($domain, $phpVersion)) === false) {
            $res->json(500, ['error' => 'unable to write vhost']);
            return;
        }

        $enabledPath = "/etc/nginx/sites-enabled/{$domain}.conf";
        if (!is_link($enabledPath)) {
            @symlink($vhostPath, $enabledPath);
        }

        if (!$this->writePool($domain, $phpVersion, $res)) {
            return;
        }

        [$out, $ok] = $this->run(['nginx', '-t']);
        if (!$ok) {
            $res->json(500, ['error' => "nginx config test failed: {$out}"]);
            return;
        }

        $this->run(['systemctl', 'reload', 'nginx']);
        $res->json(201, ['domain' => $domain, 'status' => 'created']);
    }

    public function delete(Request $req, Response $res, array $params): void
    {
        if (!$this->cfg->featureWeb) {
            $res->json(403, ['error' => 'web feature disabled']);
            return;
        }

        $domain = $this->normalizeDomain((string) ($params['domain'] ?? ''));
        if (!$this->isValidDomain($domain)) {
            $res->json(400, ['error' => 'invalid domain name']);
            return;
        }

        @unlink("/etc/nginx/sites-enabled/{$domain}.conf");
        @unlink("/etc/nginx/sites-available/{$domain}.conf");

        $safe = $this->poolName($domain);
        foreach ($this->listInstalledPhpVersions() as $ver) {
            $poolPath = "/etc/php/{$ver}/fpm/pool.d/{$safe}.conf";
            if (is_file($poolPath)) {
                @unlink($poolPath);
                $this->run(['systemctl', 'reload', "php{$ver}-fpm"]);
            }
        }

        $this->removeTree("/var/www/{$domain}");
        $this->run(['systemctl', 'reload', 'nginx']);
        $res->json(200, ['domain' => $domain, 'status' => 'deleted']);
    }
}
