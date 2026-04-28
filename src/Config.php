<?php

declare(strict_types=1);

namespace HostCP\Agent;

final class Config
{
    public string $apiKey;
    public int    $port;
    public string $tlsCert;
    public string $tlsKey;
    public bool   $featureWeb;
    public bool   $featureEmail;
    public bool   $featureDns;

    public static function load(string $path): self
    {
        if (!is_file($path)) {
            throw new \RuntimeException("config: file not found: {$path}");
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException("config: unable to read: {$path}");
        }

        $data = self::parseSimpleYaml($raw);

        $cfg               = new self();
        $cfg->apiKey       = (string) ($data['api_key'] ?? '');
        $cfg->port         = (int) ($data['port'] ?? 8443);
        $cfg->tlsCert      = (string) ($data['tls_cert'] ?? '/etc/hostcp-agent/tls/cert.pem');
        $cfg->tlsKey       = (string) ($data['tls_key'] ?? '/etc/hostcp-agent/tls/key.pem');
        $features          = is_array($data['features'] ?? null) ? $data['features'] : [];
        $cfg->featureWeb   = (bool) ($features['web'] ?? false);
        $cfg->featureEmail = (bool) ($features['email'] ?? false);
        $cfg->featureDns   = (bool) ($features['dns'] ?? false);

        if ($cfg->apiKey === '') {
            throw new \RuntimeException('config: api_key must not be empty');
        }

        return $cfg;
    }

    private static function parseSimpleYaml(string $raw): array
    {
        $out        = [];
        $inFeatures = false;

        foreach (preg_split('/\r?\n/', $raw) as $line) {
            $trim = trim($line);
            if ($trim === '' || str_starts_with($trim, '#')) {
                continue;
            }

            if (preg_match('/^features:\s*$/', $trim) === 1) {
                $inFeatures      = true;
                $out['features'] = $out['features'] ?? [];
                continue;
            }

            if ($inFeatures && preg_match('/^\s{2}([a-z_]+):\s*(.+)$/i', $line, $m) === 1) {
                $out['features'][$m[1]] = self::parseScalar($m[2]);
                continue;
            }

            $inFeatures = false;
            if (preg_match('/^([a-z_]+):\s*(.+)$/i', $trim, $m) === 1) {
                $out[$m[1]] = self::parseScalar($m[2]);
            }
        }

        return $out;
    }

    private static function parseScalar(string $value): mixed
    {
        $v = trim($value);
        if (
            (str_starts_with($v, '"') && str_ends_with($v, '"'))
            || (str_starts_with($v, "'") && str_ends_with($v, "'"))
        ) {
            $v = substr($v, 1, -1);
        }

        $lower = strtolower($v);
        if ($lower === 'true') {
            return true;
        }
        if ($lower === 'false') {
            return false;
        }
        if (preg_match('/^-?\d+$/', $v) === 1) {
            return (int) $v;
        }

        return $v;
    }
}
