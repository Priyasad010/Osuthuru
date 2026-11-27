<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\Request;

/**
 * Hosting behind the provider's own proxy.
 */
class SharedHostingTest extends TestCase
{
    private const SERVER = [
        'REMOTE_ADDR' => '10.0.0.5',
        'HTTP_X_REAL_IP' => '203.0.113.7',
        'HTTP_X_FORWARDED_FOR' => '198.51.100.9',
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ];

    public function testProxyHeadersAreIgnoredUnlessConfigured(): void
    {
        $this->assertSame(self::SERVER, Request::applyProxy(self::SERVER, ''));
    }

    public function testTheNamedHeaderGivesTheVisitorAddressAndHttps(): void
    {
        $server = Request::applyProxy(self::SERVER, 'X-Real-IP');
        $this->assertSame('203.0.113.7', Request::clientIp($server));
        $this->assertSame('on', $server['HTTPS']);
    }

    public function testBadOrMissingValuesChangeNothing(): void
    {
        $server = Request::applyProxy(['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_REAL_IP' => 'not-an-address', 'HTTP_X_FORWARDED_PROTO' => 'http'], 'X-Real-IP');
        $this->assertSame('10.0.0.5', $server['REMOTE_ADDR']);
        $this->assertArrayNotHasKey('HTTPS', $server);
        $this->assertSame('10.0.0.5', Request::applyProxy(['REMOTE_ADDR' => '10.0.0.5'], 'X-Real-IP')['REMOTE_ADDR']);
    }

    public function testSettingsTemplateTurnsTheMlServiceOffAndDebuggingOff(): void
    {
        $template = (string)file_get_contents(dirname(__DIR__) . '/.env.alwaysdata.example');
        $this->assertMatchesRegularExpression('/^ML_URL=\r?$/m', $template);
        $this->assertMatchesRegularExpression('/^APP_DEBUG=false\r?$/m', $template);
        $this->assertMatchesRegularExpression('/^APP_ENV=production\r?$/m', $template);
    }
}
