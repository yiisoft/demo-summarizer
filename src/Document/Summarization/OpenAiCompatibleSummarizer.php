<?php

declare(strict_types=1);

namespace App\Document\Summarization;

use RuntimeException;

use function error_clear_last;
use function error_get_last;
use function file_get_contents;
use function json_decode;
use function json_encode;
use function mb_substr;
use function rtrim;
use function sprintf;
use function stream_context_create;
use function trim;

/**
 * Requests document summaries from an OpenAI-compatible chat completions API.
 */
final readonly class OpenAiCompatibleSummarizer implements SummarizerInterface
{
    /**
     * @param string $baseUrl Base URL of the OpenAI-compatible API, including the API version prefix.
     * @param string $model Model name sent in the chat completions request.
     * @param string $apiKey Optional bearer token for protected local or remote endpoints.
     */
    public function __construct(
        private string $baseUrl,
        private string $model,
        private string $apiKey = '',
    ) {}

    /**
     * Requests a concise document summary.
     *
     * @param string $markdown Extracted document markdown.
     * @param string $documentName Original document filename.
     */
    public function summarize(string $markdown, string $documentName): string
    {
        $payload = json_encode([
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Summarize documents for a Yii3 demo. Return concise, useful bullet points.',
                ],
                [
                    'role' => 'user',
                    'content' => "Document: {$documentName}\n\n" . mb_substr($markdown, 0, 12000),
                ],
            ],
            'stream' => false,
            'temperature' => 0.2,
            'max_tokens' => 512,
        ]);

        if ($payload === false) {
            throw new RuntimeException('Unable to encode OpenAI-compatible request.');
        }

        $url = rtrim($this->baseUrl, '/') . '/chat/completions';
        $headers = "Content-Type: application/json\n";
        if ($this->apiKey !== '') {
            $headers .= "Authorization: Bearer {$this->apiKey}\n";
        }

        error_clear_last();
        $body = @file_get_contents(
            $url,
            false,
            stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => $headers,
                    'content' => $payload,
                    'timeout' => 120,
                ],
            ]),
        );

        if ($body === false) {
            $error = error_get_last()['message'] ?? 'No transport error details were available.';
            throw new RuntimeException(sprintf(
                'OpenAI-compatible summary request to %s failed: %s.',
                $url,
                $error,
            ));
        }

        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('OpenAI-compatible API returned an unexpected response.');
        }

        $choices = $decoded['choices'] ?? null;
        if (!is_array($choices)) {
            throw new RuntimeException('OpenAI-compatible API returned an unexpected response.');
        }

        $firstChoice = $choices[0] ?? null;
        if (!is_array($firstChoice)) {
            throw new RuntimeException('OpenAI-compatible API returned an unexpected response.');
        }

        $message = $firstChoice['message'] ?? null;
        if (!is_array($message)) {
            throw new RuntimeException('OpenAI-compatible API returned an unexpected response.');
        }

        $content = $message['content'] ?? null;
        if (!is_string($content)) {
            throw new RuntimeException('OpenAI-compatible API returned an unexpected response.');
        }

        return trim($content);
    }
}
