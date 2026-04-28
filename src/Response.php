<?php

declare(strict_types=1);

namespace HostCP\Agent;

final class Response
{
    public function json(int $status, array $data): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
    }
}
