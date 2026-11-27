<?php

namespace App\Core;

class Request
{
    /**
     * IP address of the connecting client.
     * Forwarding headers such as X-Forwarded-For are chosen by the client, so they
     * are ignored. If the app is ever put behind a reverse proxy, configure the web
     * server to rewrite REMOTE_ADDR for that proxy only.
     */
    /**
     * For a hosting company whose own proxy handles HTTPS and the web server cannot
     * be configured. Only used when PROXY_IP_HEADER is set in .env, naming the header
     * that proxy always overwrites with the visitor's address (for example X-Real-IP).
     * Never set it when visitors can reach the web server directly.
     */
    public static function applyProxy(array $server, string $ipHeader): array
    {
        if ($ipHeader === '') {
            return $server;
        }
        $ip = trim((string)($server['HTTP_' . strtoupper(str_replace('-', '_', $ipHeader))] ?? ''));
        if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
            $server['REMOTE_ADDR'] = $ip;
        }
        if (strtolower((string)($server['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
            $server['HTTPS'] = 'on';
        }
        return $server;
    }

    public static function clientIp(array $server): string
    {
        return (string)($server['REMOTE_ADDR'] ?? '127.0.0.1');
    }
}
