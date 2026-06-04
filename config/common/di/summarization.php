<?php

declare(strict_types=1);

use App\Document\Summarization\ConfiguredSummarizer;
use App\Document\Summarization\OllamaSummarizer;
use App\Document\Summarization\SummarizerInterface;

/** @var array $params */

return [
    ConfiguredSummarizer::class => [
        '__construct()' => [
            'llmAdapter' => $params['documentDemo']['llmAdapter'],
        ],
    ],
    OllamaSummarizer::class => [
        '__construct()' => [
            'baseUrl' => $params['documentDemo']['ollamaBaseUrl'],
            'model' => $params['documentDemo']['ollamaModel'],
        ],
    ],
    SummarizerInterface::class => ConfiguredSummarizer::class,
];
