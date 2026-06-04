<?php

declare(strict_types=1);

use App\Document\DocumentDemoConfig;
use App\Document\Extraction\ConfiguredExtractor;
use App\Document\Extraction\ExtractorInterface;
use App\Document\Infrastructure\DocumentStorageFactory;
use App\Document\Infrastructure\DocumentStorageInterface;
use App\Document\Processing\ConfiguredDocumentQueue;
use App\Document\Processing\DocumentQueueInterface;
use App\Document\Summarization\ConfiguredSummarizer;
use App\Document\Summarization\SummarizerInterface;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;

/** @var array $params */

return [
    DocumentDemoConfig::class => [
        '__construct()' => $params['documentDemo'],
    ],
    ConnectionInterface::class => static function (SchemaCache $schemaCache) use ($params): ConnectionInterface {
        $dsn = $params['documentDemo']['databaseDsn'];
        if (str_starts_with($dsn, 'sqlite:')) {
            $path = substr($dsn, 7);
            if ($path !== ':memory:') {
                $directory = dirname($path);
                if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                    throw new \RuntimeException("Unable to create database directory \"$directory\".");
                }
            }
        }

        return new Connection(new Driver($dsn), $schemaCache);
    },
    DocumentStorageInterface::class => static fn (DocumentStorageFactory $factory): DocumentStorageInterface => $factory->create(),
    ExtractorInterface::class => ConfiguredExtractor::class,
    SummarizerInterface::class => ConfiguredSummarizer::class,
    DocumentQueueInterface::class => ConfiguredDocumentQueue::class,
];
