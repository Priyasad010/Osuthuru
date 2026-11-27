<?php

/**
 * Send one test question to the configured model and report what happened.
 * The API key is never printed.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Env;
use App\Llm\GeminiClient;
use App\Service\AssistantService;

Env::load(dirname(__DIR__));

$client = GeminiClient::fromEnv();
if ($client === null) {
    echo "No model is configured. Set LLM_PROVIDER=gemini, LLM_MODEL and LLM_API_KEY in .env.\n";
    exit(1);
}

echo 'Models: ' . implode(', then ', $client->models()) . "\n";

try {
    $start = microtime(true);
    $raw = $client->generate(
        'Respond with only a JSON object: {"reply": "text", "product_ids": []}',
        [['role' => 'user', 'content' => 'Reply with the single word OK.']]
    );
    $answer = AssistantService::parseAnswer($raw, []);
    if ($answer === null) {
        echo "The model answered, but not in the expected JSON form:\n" . mb_substr($raw, 0, 300) . "\n";
        exit(1);
    }
    printf("OK in %.1f s from %s. Reply: %s\n", microtime(true) - $start, $client->answeredBy, $answer['reply']);
    exit(0);
} catch (Throwable $e) {
    echo 'FAILED: ' . $e->getMessage() . "\n";
    exit(1);
}
