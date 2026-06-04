<?php

declare(strict_types=1);

namespace App\Document\Summarization;

use RuntimeException;

use function file_get_contents;
use function json_decode;
use function json_encode;
use function mb_substr;
use function stream_context_create;
use function trim;

final readonly class OllamaSummarizer implements SummarizerInterface
{
    public function __construct(
        private string $baseUrl,
        private string $model,
    ) {}

    public function summarize(string $markdown, string $documentName): string
    {
        $payload = json_encode([
            'model' => $this->model,
            'stream' => false,
            'prompt' => "Summarize this document in 5 concise bullet points.\n\n"
                . "Document: $documentName\n\n"
                . mb_substr($markdown, 0, 24000),
        ]);
        if ($payload === false) {
            throw new RuntimeException('Unable to encode Ollama request.');
        }

        $body = file_get_contents(
            rtrim($this->baseUrl, '/') . '/api/generate',
            false,
            stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\n",
                    'content' => $payload,
                    'timeout' => 120,
                ],
            ]),
        );

        if ($body === false) {
            throw new RuntimeException('Ollama request failed.');
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded) || !isset($decoded['response'])) {
            throw new RuntimeException('Ollama returned an unexpected response.');
        }

        return trim((string) $decoded['response']);
    }
}
