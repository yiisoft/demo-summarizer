<?php

declare(strict_types=1);

use App\Document\DocumentDemoConfig;
use App\Document\Extraction\ConfiguredExtractor;
use App\Document\Extraction\ExtractorInterface;
use App\Document\Infrastructure\DocumentDatabase;
use App\Document\Infrastructure\DocumentStorageFactory;
use App\Document\Infrastructure\DocumentStorageInterface;
use App\Document\Processing\ConfiguredDocumentQueue;
use App\Document\Processing\DocumentQueueInterface;
use App\Document\Summarization\ConfiguredSummarizer;
use App\Document\Summarization\SummarizerInterface;

/** @var array $params */

return [
    DocumentDemoConfig::class => [
        '__construct()' => $params['documentDemo'],
    ],
    DocumentDatabase::class => [
        '__construct()' => [
            'dsn' => $params['documentDemo']['databaseDsn'],
        ],
    ],
    DocumentStorageInterface::class => static fn (DocumentStorageFactory $factory): DocumentStorageInterface => $factory->create(),
    ExtractorInterface::class => ConfiguredExtractor::class,
    SummarizerInterface::class => ConfiguredSummarizer::class,
    DocumentQueueInterface::class => ConfiguredDocumentQueue::class,
];
