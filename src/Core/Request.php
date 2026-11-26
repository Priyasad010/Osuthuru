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
    public static function clientIp(array $server): string
    {
        return (string)($server['REMOTE_ADDR'] ?? '127.0.0.1');
    }
}
