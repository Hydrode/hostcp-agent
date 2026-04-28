<?php

declare(strict_types=1);

namespace HostCP\Agent;

use HostCP\Agent\Middleware\AuthMiddleware;

final class Router
{
    /** @var list<array{0:string,1:string,2:array{0:string,1:string}}> */
    private array $routes = [];

    public function add(string $method, string $pattern, string $handlerClass, string $action): void
    {
        $regex          = preg_replace('/\{([a-z_]+)\}/', '(?P<$1>[^/]+)', $pattern);
        $this->routes[] = [strtoupper($method), '#^' . $regex . '$#', [$handlerClass, $action]];
    }

    public function dispatch(Request $req, Response $res, Config $cfg, AuthMiddleware $auth): void
    {
        $auth->handle($req, $res);

        foreach ($this->routes as [$method, $regex, $handler]) {
            if ($req->method() === $method && preg_match($regex, $req->path(), $m) === 1) {
                $params           = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                [$class, $action] = $handler;
                (new $class($cfg))->$action($req, $res, $params);
                return;
            }
        }

        $res->json(404, ['error' => 'not found']);
    }
}
