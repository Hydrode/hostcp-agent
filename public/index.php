<?php

declare(strict_types=1);

namespace HostCP\Agent;

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'HostCP\\Agent\\')) {
        $path = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, 14)) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    }
});

try {
    $config = Config::load('/etc/hostcp-agent/config.yaml');
    $req    = new Request();
    $res    = new Response();
    $auth   = new Middleware\AuthMiddleware($config);
    $router = new Router();

    // Register routes
    $router->add('GET', '/api/health', Handlers\HealthHandler::class, 'health');
    $router->add('GET', '/api/monitor', Handlers\HealthHandler::class, 'monitor');
    $router->add('POST', '/api/websites', Handlers\WebsiteHandler::class, 'create');
    $router->add('DELETE', '/api/websites/{domain}', Handlers\WebsiteHandler::class, 'delete');
    $router->add('POST', '/api/ssl/issue', Handlers\SslHandler::class, 'issue');
    $router->add('POST', '/api/ssl/renew', Handlers\SslHandler::class, 'renew');
    $router->add('POST', '/api/ssl/revoke', Handlers\SslHandler::class, 'revoke');
    $router->add('GET', '/api/php/versions', Handlers\PhpHandler::class, 'versions');
    $router->add('POST', '/api/php/switch', Handlers\PhpHandler::class, 'switchVersion');
    $router->add('GET', '/api/vhost/{domain}', Handlers\VhostHandler::class, 'show');
    $router->add('PUT', '/api/vhost/{domain}', Handlers\VhostHandler::class, 'update');
    $router->add('GET', '/api/websites/{domain}/files', Handlers\FileHandler::class, 'list');
    $router->add('POST', '/api/websites/{domain}/files', Handlers\FileHandler::class, 'upload');
    $router->add('GET', '/api/websites/{domain}/logs', Handlers\LogHandler::class, 'get');

    $router->dispatch($req, $res, $config, $auth);
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Internal Server Error']);
    error_log((string) $e);
}
