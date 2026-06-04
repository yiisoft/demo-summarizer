<?php

declare(strict_types=1);

/**
 * @var Closure(non-empty-string, string): string $env
 */

return [
    'llmAdapter' => $env('LLM_ADAPTER', 'mock'),
    'ollamaBaseUrl' => $env('OLLAMA_BASE_URL', 'http://ollama:11434'),
    'ollamaModel' => $env('OLLAMA_MODEL', 'llama3.2'),
    'llmProvider' => $env('LLM_PROVIDER', ''),
    'llmApiKey' => $env('LLM_API_KEY', ''),
    'llmModel' => $env('LLM_MODEL', ''),
];
