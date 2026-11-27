<?php

namespace App\Llm;

use App\Core\Env;
use Closure;
use RuntimeException;

/**
 * Server-side Gemini call over cURL. The API key is sent only in a request
 * header: it is never put in a URL, logged or returned to the browser.
 *
 * When the main model is busy, slow or withdrawn, a second model is tried. Both
 * attempts together stay within the time limit.
 */
class GeminiClient implements LlmClient
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private const TIMEOUT_SECONDS = 15;
    /** Longest wait for the main model when there is another one to try. */
    private const FIRST_ATTEMPT_SECONDS = 8;
    /** Worth trying elsewhere: no answer, unknown model, rate limit or server trouble. */
    private const RETRY_STATUSES = [0, 404, 429, 500, 502, 503, 504];

    /** The model that gave the last answer. */
    public ?string $answeredBy = null;

    private Closure $transport;

    /**
     * @param callable|null $transport fn(string $url, string $json, array $headers, int $seconds): array{0:int,1:string}
     *                                 returning (status, body or error text); for tests
     */
    public function __construct(
        private string $apiKey,
        private string $model,
        private ?string $fallbackModel = null,
        ?callable $transport = null
    ) {
        $this->transport = $transport ? Closure::fromCallable($transport) : Closure::fromCallable([$this, 'send']);
    }

    /** The configured client, or null when the assistant has no key. */
    public static function fromEnv(): ?self
    {
        $key = trim((string)Env::get('LLM_API_KEY', ''));
        $model = trim((string)Env::get('LLM_MODEL', 'gemini-3.5-flash-lite'));
        $fallback = trim((string)Env::get('LLM_FALLBACK_MODEL', 'gemini-3.5-flash'));
        $provider = strtolower((string)Env::get('LLM_PROVIDER', 'gemini'));

        return ($key !== '' && $model !== '' && $provider === 'gemini') ? new self($key, $model, $fallback ?: null) : null;
    }

    /** The models in the order they are tried. */
    public function models(): array
    {
        return array_values(array_unique(array_filter([$this->model, $this->fallbackModel])));
    }

    public function url(?string $model = null): string
    {
        return self::ENDPOINT . rawurlencode($model ?? $this->model) . ':generateContent';
    }

    public function requestBody(string $system, array $messages): array
    {
        return [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => array_map(fn(array $m) => [
                'role' => $m['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $m['content']]],
            ], $messages),
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'maxOutputTokens' => 4096,
                'thinkingConfig' => ['thinkingLevel' => 'low'],
            ],
        ];
    }

    /** Text of the first candidate, without the model's "thought" parts. */
    public static function extractText(array $response): string
    {
        $text = '';
        foreach ($response['candidates'][0]['content']['parts'] ?? [] as $part) {
            if (empty($part['thought']) && isset($part['text'])) {
                $text .= $part['text'];
            }
        }
        if (trim($text) === '') {
            throw new RuntimeException('The model returned no text.');
        }
        return $text;
    }

    public function generate(string $system, array $messages): string
    {
        $json = json_encode($this->requestBody($system, $messages), JSON_THROW_ON_ERROR);
        $headers = ['Content-Type: application/json', 'x-goog-api-key: ' . $this->apiKey];
        $models = $this->models();
        $deadline = microtime(true) + self::TIMEOUT_SECONDS;
        $failures = [];
        $this->answeredBy = null;

        foreach ($models as $i => $model) {
            $remaining = (int)floor($deadline - microtime(true));
            if ($remaining < 1) {
                break;
            }
            $seconds = $i < count($models) - 1 ? min(self::FIRST_ATTEMPT_SECONDS, $remaining) : $remaining;
            [$status, $body] = ($this->transport)($this->url($model), $json, $headers, $seconds);

            $data = json_decode($body, true);
            if ($status === 200 && is_array($data)) {
                $text = self::extractText($data);
                $this->answeredBy = $model;
                return $text;
            }

            $reason = $status === 0 ? $body : (string)($data['error']['message'] ?? 'unexpected response');
            $failures[] = "{$model}: " . ($status === 0 ? 'request failed: ' : "HTTP {$status}: ") . mb_substr($reason, 0, 200);
            if (!in_array($status, self::RETRY_STATUSES, true)) {
                break; // for example a wrong key: another model would fail the same way
            }
        }

        throw new RuntimeException(str_replace($this->apiKey, '[key]', implode(' | ', $failures) ?: 'No time left for the request.'));
    }

    private function send(string $url, string $json, array $headers, int $seconds): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(5, $seconds),
            CURLOPT_TIMEOUT => $seconds,
        ]);

        $body = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        return $body === false ? [0, $error] : [$status, (string)$body];
    }
}
