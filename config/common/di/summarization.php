<?php

declare(strict_types=1);

use App\Document\Summarization\ConfiguredSummarizer;
use App\Document\Summarization\OpenAiCompatibleSummarizer;
use App\Document\Summarization\SummarizerInterface;

/** @var array $params */

return [
    ConfiguredSummarizer::class => [
        '__construct()' => [
            'llmAdapter' => $params['documentDemo']['llmAdapter'],
        ],
    ],
    OpenAiCompatibleSummarizer::class => [
        '__construct()' => [
            'baseUrl' => $params['documentDemo']['llmBaseUrl'],
            'model' => $params['documentDemo']['llmModel'],
            'apiKey' => $params['documentDemo']['llmApiKey'],
        ],
    ],
    SummarizerInterface::class => ConfiguredSummarizer::class,
];
