<?php

declare(strict_types=1);

namespace HostCP\Agent\Middleware;

use HostCP\Agent\Config;
use HostCP\Agent\Request;
use HostCP\Agent\Response;

final class AuthMiddleware
{
    public function __construct(private Config $cfg) {}

    public function handle(Request $req, Response $res): void
    {
        $auth = $req->header('Authorization');
        if (!str_starts_with($auth, 'Bearer ') || !hash_equals($this->cfg->apiKey, substr($auth, 7))) {
            $res->json(401, ['error' => 'unauthorized']);
            exit;
        }
    }
}
