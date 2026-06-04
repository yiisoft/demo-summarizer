<?php

declare(strict_types=1);

use App\Document\Summarization\MockSummarizer;
use App\Document\Summarization\OpenAiCompatibleSummarizer;
use App\Document\Summarization\SummarizerInterface;

/** @var array $params */

return [
    OpenAiCompatibleSummarizer::class => [
        '__construct()' => [
            'baseUrl' => $params['documentDemo']['llmBaseUrl'],
            'model' => $params['documentDemo']['llmModel'],
            'apiKey' => $params['documentDemo']['llmApiKey'],
        ],
    ],
    SummarizerInterface::class => static function (
        MockSummarizer $mockSummarizer,
        OpenAiCompatibleSummarizer $openAiCompatibleSummarizer,
    ) use ($params): SummarizerInterface {
        return match ($params['documentDemo']['llmAdapter']) {
            'mock' => $mockSummarizer,
            'llamacpp' => $openAiCompatibleSummarizer,
            default => throw new RuntimeException('LLM_ADAPTER must be mock or llamacpp.'),
        };
    },
];
