<?php

declare(strict_types=1);

namespace HostCP\Agent\Handlers;

use HostCP\Agent\Config;
use HostCP\Agent\Response;

abstract class AbstractHandler
{
    private const DOMAIN_RE = '/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?)*$/';

    public function __construct(protected Config $cfg) {}

    protected function normalizeDomain(string $domain): string
    {
        return strtolower(trim($domain));
    }

    protected function isValidDomain(string $domain): bool
    {
        return $domain !== '' && strlen($domain) <= 253 && preg_match(self::DOMAIN_RE, $domain) === 1;
    }

    protected function poolName(string $domain): string
    {
        return str_replace('.', '-', $domain);
    }

    protected function listInstalledPhpVersions(string $baseDir = '/etc/php'): array
    {
        if (!is_dir($baseDir)) {
            return [];
        }
        $entries = scandir($baseDir);
        if ($entries === false) {
            return [];
        }
        $versions = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (is_dir($baseDir . DIRECTORY_SEPARATOR . $entry) && preg_match('/^\d+\.\d+$/', $entry) === 1) {
                $versions[] = $entry;
            }
        }
        sort($versions, SORT_NATURAL);
        return $versions;
    }

    protected function writePool(string $domain, string $phpVersion, Response $res): bool
    {
        $safe     = $this->poolName($domain);
        $poolPath = "/etc/php/{$phpVersion}/fpm/pool.d/{$safe}.conf";

        if (file_put_contents($poolPath, $this->generatePool($domain, $phpVersion)) === false) {
            $res->json(500, ['error' => 'unable to write fpm pool']);
            return false;
        }

        [, $ok] = $this->run(['systemctl', 'reload', "php{$phpVersion}-fpm"]);
        if (!$ok) {
            $res->json(500, ['error' => "unable to reload php{$phpVersion}-fpm"]);
            return false;
        }

        return true;
    }

    protected function generatePool(string $domain, string $phpVersion): string
    {
        $safe = $this->poolName($domain);
        return "[{$safe}]\n"
            . "user = www-data\n"
            . "group = www-data\n"
            . "listen = /run/php/php{$phpVersion}-fpm-{$safe}.sock\n"
            . "listen.owner = www-data\n"
            . "listen.group = www-data\n"
            . "pm = dynamic\n"
            . "pm.max_children = 5\n"
            . "pm.start_servers = 2\n"
            . "pm.min_spare_servers = 1\n"
            . "pm.max_spare_servers = 3\n"
            . "chdir = /\n";
    }

    protected function generateVhost(string $domain, string $phpVersion): string
    {
        $safe = $this->poolName($domain);
        return "server {\n"
            . "    listen 80;\n"
            . "    server_name {$domain} www.{$domain};\n"
            . "    root /var/www/{$domain}/public_html;\n"
            . "    index index.php index.html;\n\n"
            . "    access_log /var/log/nginx/{$domain}.access.log;\n"
            . "    error_log /var/log/nginx/{$domain}.error.log;\n\n"
            . "    location / {\n"
            . "        try_files \$uri \$uri/ /index.php?\$query_string;\n"
            . "    }\n\n"
            . "    location ~ \\.php\$ {\n"
            . "        fastcgi_pass unix:/run/php/php{$phpVersion}-fpm-{$safe}.sock;\n"
            . "        fastcgi_index index.php;\n"
            . "        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;\n"
            . "        include fastcgi_params;\n"
            . "    }\n\n"
            . "    location ~ /\\.(?!well-known).* {\n"
            . "        deny all;\n"
            . "    }\n"
            . "}\n";
    }

    protected function safeJoin(string $root, string $subPath): ?string
    {
        $root  = rtrim($root, DIRECTORY_SEPARATOR);
        $parts = preg_split('#[\\/]+#', $subPath) ?: [];
        $clean = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                return null;
            }
            $clean[] = $part;
        }

        return $root . (empty($clean) ? '' : DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $clean));
    }

    protected function tailFile(string $path, int $maxLines): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }
        $buffer = [];
        while (($line = fgets($handle)) !== false) {
            $buffer[] = rtrim($line, "\r\n");
            if (count($buffer) > $maxLines) {
                array_shift($buffer);
            }
        }
        fclose($handle);
        return $buffer;
    }

    protected function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($full) && !is_link($full)) {
                $this->removeTree($full);
            } else {
                @unlink($full);
            }
        }
        @rmdir($path);
    }

    /**
     * @param list<string> $cmd
     * @return array{0:string,1:bool}
     */
    protected function run(array $cmd): array
    {
        $escaped  = array_map(static fn(string $p): string => escapeshellarg($p), $cmd);
        $command  = implode(' ', $escaped) . ' 2>&1';
        $output   = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        return [trim(implode("\n", $output)), $exitCode === 0];
    }
}
