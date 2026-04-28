<?php

declare(strict_types=1);

namespace HostCP\Agent\Handlers;

use HostCP\Agent\Request;
use HostCP\Agent\Response;

final class FileHandler extends AbstractHandler
{
    public function list(Request $req, Response $res, array $params): void
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

        $docRoot = "/var/www/{$domain}/public_html";
        if (!is_dir($docRoot)) {
            $res->json(404, ['error' => 'domain not found']);
            return;
        }

        $path = (string) ($req->query('path', '/'));
        $fullPath = realpath($docRoot . $path);
        
        if ($fullPath === false || !str_starts_with($fullPath, realpath($docRoot) ?: '')) {
            $res->json(400, ['error' => 'invalid path']);
            return;
        }

        if (!is_dir($fullPath)) {
            $res->json(400, ['error' => 'not a directory']);
            return;
        }

        $items = [];
        foreach (scandir($fullPath) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $itemPath = $fullPath . DIRECTORY_SEPARATOR . $item;
            $items[] = [
                'name' => $item,
                'type' => is_dir($itemPath) ? 'dir' : 'file',
                'size' => is_file($itemPath) ? (int) filesize($itemPath) : 0,
            ];
        }

        $res->json(200, ['files' => $items]);
    }

    public function upload(Request $req, Response $res, array $params): void
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

        if (empty($_FILES)) {
            $res->json(400, ['error' => 'no file provided']);
            return;
        }

        $file = reset($_FILES);
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $res->json(400, ['error' => 'invalid upload']);
            return;
        }

        $docRoot = "/var/www/{$domain}/public_html";
        if (!is_dir($docRoot)) {
            $res->json(404, ['error' => 'domain not found']);
            return;
        }

        $filename = basename((string) ($file['name'] ?? 'upload'));
        $dest = $docRoot . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $res->json(500, ['error' => 'upload failed']);
            return;
        }

        $res->json(200, ['uploaded' => $filename, 'path' => "/{$filename}"]);
    }
}
