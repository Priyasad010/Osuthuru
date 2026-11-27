<?php

namespace App\Llm;

use RuntimeException;

interface LlmClient
{
    /**
     * @param string $system   Instructions for the model.
     * @param array  $messages Oldest first: ['role' => 'user'|'assistant', 'content' => string].
     * @return string The model's text, expected to be a JSON object.
     * @throws RuntimeException when no usable answer is received.
     */
    public function generate(string $system, array $messages): string;
}
