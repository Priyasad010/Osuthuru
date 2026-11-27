<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Llm\GeminiClient;
use RuntimeException;

class GeminiFallbackTest extends TestCase
{
    private const OK = '{"candidates":[{"content":{"parts":[{"text":"answer"}]}}]}';

    /** A client whose attempts get these [status, body] results in turn. */
    private function client(array $results, ?array &$calls, ?string $fallback = 'second'): GeminiClient
    {
        $calls = [];
        return new GeminiClient('SECRET-KEY', 'first', $fallback, function ($url, $json, $headers, $seconds) use (&$results, &$calls) {
            $calls[] = [$url, $seconds];
            return array_shift($results);
        });
    }

    public function testMainModelAnswers(): void
    {
        $client = $this->client([[200, self::OK]], $calls);

        $this->assertSame('answer', $client->generate('s', [['role' => 'user', 'content' => 'q']]));
        $this->assertSame('first', $client->answeredBy);
        $this->assertCount(1, $calls);
        $this->assertLessThanOrEqual(8, $calls[0][1]);
    }

    /** @dataProvider temporaryFailures */
    public function testSecondModelIsTriedAfterATemporaryFailure(int $status): void
    {
        $client = $this->client([[$status, 'busy'], [200, self::OK]], $calls);

        $this->assertSame('answer', $client->generate('s', [['role' => 'user', 'content' => 'q']]));
        $this->assertSame('second', $client->answeredBy);
        $this->assertStringContainsString('/models/second:', $calls[1][0]);
        $this->assertLessThanOrEqual(15, $calls[1][1]);
    }

    public static function temporaryFailures(): array
    {
        return ['no answer' => [0], 'withdrawn' => [404], 'rate limit' => [429], 'overloaded' => [503]];
    }

    public function testWrongKeyIsNotRetriedAndNeverShown(): void
    {
        $client = $this->client([[400, '{"error":{"message":"Bad key SECRET-KEY"}}'], [200, self::OK]], $calls);

        try {
            $client->generate('s', [['role' => 'user', 'content' => 'q']]);
            $this->fail('Expected a failure');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('HTTP 400', $e->getMessage());
            $this->assertStringNotContainsString('SECRET-KEY', $e->getMessage());
        }
        $this->assertCount(1, $calls);
    }

    public function testBothFailuresAreReported(): void
    {
        $client = $this->client([[503, '{"error":{"message":"busy"}}'], [0, 'timed out']], $calls);

        $this->expectExceptionMessageMatches('/first: HTTP 503.*second: request failed: timed out/');
        $client->generate('s', [['role' => 'user', 'content' => 'q']]);
    }

    public function testWithoutASecondModelTheWholeTimeIsUsed(): void
    {
        $client = $this->client([[200, self::OK]], $calls, null);
        $client->generate('s', [['role' => 'user', 'content' => 'q']]);

        $this->assertSame(['first'], $client->models());
        $this->assertGreaterThan(8, $calls[0][1]);
        $this->assertSame(['first'], (new GeminiClient('k', 'first', 'first'))->models());
    }
}
