<?php

declare(strict_types=1);

namespace HostCP\Agent\Handlers;

use HostCP\Agent\Request;
use HostCP\Agent\Response;

final class SslHandler extends AbstractHandler
{
    public function issue(Request $req, Response $res, array $params): void
    {
        $body   = $req->body();
        $domain = $this->normalizeDomain((string) ($body['domain'] ?? ''));
        $email  = trim((string) ($body['email'] ?? ''));

        if (!$this->isValidDomain($domain) || $email === '') {
            $res->json(400, ['error' => 'domain and email required']);
            return;
        }

        [$out, $ok] = $this->run([
            'certbot', 'certonly', '--nginx',
            '-d', $domain, '-d', "www.{$domain}",
            '--non-interactive', '--agree-tos', '-m', $email,
        ]);

        if (!$ok) {
            $res->json(500, ['error' => "certbot issue failed: {$out}"]);
            return;
        }

        $res->json(200, ['status' => 'issued']);
    }

    public function renew(Request $req, Response $res, array $params): void
    {
        $body   = $req->body();
        $domain = $this->normalizeDomain((string) ($body['domain'] ?? ''));

        if (!$this->isValidDomain($domain)) {
            $res->json(400, ['error' => 'domain required']);
            return;
        }

        [$out, $ok] = $this->run(['certbot', 'renew', '--cert-name', $domain, '--non-interactive']);
        if (!$ok) {
            $res->json(500, ['error' => "certbot renew failed: {$out}"]);
            return;
        }

        $res->json(200, ['status' => 'renewed']);
    }

    public function revoke(Request $req, Response $res, array $params): void
    {
        $body   = $req->body();
        $domain = $this->normalizeDomain((string) ($body['domain'] ?? ''));

        if (!$this->isValidDomain($domain)) {
            $res->json(400, ['error' => 'domain required']);
            return;
        }

        [$out, $ok] = $this->run(['certbot', 'revoke', '--cert-name', $domain, '--non-interactive', '--delete-after-revoke']);
        if (!$ok) {
            $res->json(500, ['error' => "certbot revoke failed: {$out}"]);
            return;
        }

        $res->json(200, ['status' => 'revoked']);
    }
}
