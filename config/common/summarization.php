<?php

declare(strict_types=1);

/**
 * @var Closure(non-empty-string, string): string $env
 */

return [
    'llmAdapter' => $env('LLM_ADAPTER', 'llamacpp'),
    'llmBaseUrl' => $env('LLM_BASE_URL', 'http://llama:8080/v1'),
    'llmApiKey' => $env('LLM_API_KEY', ''),
    'llmModel' => $env('LLM_MODEL', 'SmolLM2-135M-Instruct-Q4_K_M'),
];
