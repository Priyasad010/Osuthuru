<?php

namespace App\Service;

use App\Core\Env;
use Closure;
use Throwable;

/**
 * Calls the Python service. Every caller must have a fallback: get() returns
 * null whenever the service cannot give a good answer.
 */
class MlClient
{
    private const TIMEOUT_SECONDS = 3;
    /** After a connection failure, skip the service briefly so pages stay fast. */
    private const PAUSE_SECONDS = 30;

    private Closure $transport;

    /** @param callable|null $transport fn(string $url): array{0:int,1:string} (status, body); for tests */
    public function __construct(private string $baseUrl, ?callable $transport = null)
    {
        $this->transport = $transport ? Closure::fromCallable($transport) : Closure::fromCallable([$this, 'send']);
    }

    public static function fromEnv(): self
    {
        return new self(trim((string)Env::get('ML_URL', 'http://ml:8000')));
    }

    /** The response's "data", or null. */
    public function get(string $path): ?array
    {
        if ($this->baseUrl === '' || $this->isPaused()) {
            return null;
        }

        try {
            [$status, $body] = ($this->transport)(rtrim($this->baseUrl, '/') . $path);
        } catch (Throwable $e) {
            error_log('ML service unavailable: ' . $e->getMessage());
            $this->pause();
            return null;
        }

        $json = json_decode($body, true);
        if ($status !== 200 || !is_array($json) || ($json['ok'] ?? false) !== true || !is_array($json['data'] ?? null)) {
            return null;
        }
        return $json['data'];
    }

    private function send(string $url): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
        ]);
        $body = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false) {
            throw new \RuntimeException($error);
        }
        return [$status, (string)$body];
    }

    private function pauseFile(): string
    {
        return sys_get_temp_dir() . '/osuthuru_ml_down_' . md5($this->baseUrl);
    }

    private function isPaused(): bool
    {
        $file = $this->pauseFile();
        clearstatcache(true, $file);
        return is_file($file) && time() - (int)filemtime($file) < self::PAUSE_SECONDS;
    }

    private function pause(): void
    {
        @touch($this->pauseFile());
    }
}
