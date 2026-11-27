<?php

namespace App\Llm;

use App\Core\Env;
use RuntimeException;

/**
 * Server-side Gemini call over cURL. The API key is sent only in a request
 * header: it is never put in a URL, logged or returned to the browser.
 */
class GeminiClient implements LlmClient
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private const TIMEOUT_SECONDS = 15;

    public function __construct(private string $apiKey, private string $model)
    {
    }

    /** The configured client, or null when the assistant has no key. */
    public static function fromEnv(): ?self
    {
        $key = trim((string)Env::get('LLM_API_KEY', ''));
        $model = trim((string)Env::get('LLM_MODEL', 'gemini-3.8-flash'));
        $provider = strtolower((string)Env::get('LLM_PROVIDER', 'gemini'));

        return ($key !== '' && $model !== '' && $provider === 'gemini') ? new self($key, $model) : null;
    }

    public function url(): string
    {
        return self::ENDPOINT . rawurlencode($this->model) . ':generateContent';
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
        $curl = curl_init($this->url());
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($this->requestBody($system, $messages), JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $this->apiKey],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
        ]);

        $body = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false) {
            throw new RuntimeException('Request failed: ' . $error);
        }
        $data = json_decode((string)$body, true);
        if ($status !== 200 || !is_array($data)) {
            throw new RuntimeException("HTTP {$status}: " . mb_substr((string)($data['error']['message'] ?? 'unexpected response'), 0, 300));
        }

        return self::extractText($data);
    }
}
