<?php

declare(strict_types=1);

use App\Document\Extraction\ConfiguredExtractor;
use App\Document\Extraction\ExtractorInterface;
use App\Document\Infrastructure\DocumentStorageFactory;
use App\Document\Infrastructure\DocumentStorageInterface;
use App\Document\Processing\ConfiguredDocumentQueue;
use App\Document\Processing\DocumentQueueInterface;
use App\Document\Processing\DocumentProcessor;
use App\Document\Processing\DocumentUploadService;
use App\Document\Summarization\ConfiguredSummarizer;
use App\Document\Summarization\OllamaSummarizer;
use App\Document\Summarization\SummarizerInterface;
use App\Web\Document\UploadAction;
use App\Web\HomePage\Action as HomePageAction;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Psr\Log\LoggerInterface;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;
use Yiisoft\Queue\AMQP\Adapter as AmqpAdapter;
use Yiisoft\Queue\AMQP\QueueProvider as AmqpQueueProvider;
use Yiisoft\Queue\AMQP\Settings\Queue as AmqpQueueSettings;
use Yiisoft\Queue\Cli\LoopInterface;
use Yiisoft\Queue\Message\MessageSerializerInterface;
use Yiisoft\Queue\Middleware\Push\PushMiddlewareConfig;
use Yiisoft\Queue\Provider\PredefinedQueueProvider;
use Yiisoft\Queue\Provider\QueueProviderInterface;
use Yiisoft\Queue\Queue;
use Yiisoft\Queue\QueueInterface;
use Yiisoft\Queue\Redis\Adapter as RedisAdapter;
use Yiisoft\Queue\Redis\QueueProvider as RedisQueueProvider;
use Yiisoft\Queue\Worker\WorkerInterface;

/** @var array $params */

return [
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
    DocumentStorageFactory::class => [
        '__construct()' => [
            'storageDriver' => $params['documentDemo']['storageDriver'],
            'localStorageRoot' => $params['documentDemo']['localStorageRoot'],
            's3Endpoint' => $params['documentDemo']['s3Endpoint'],
            's3Region' => $params['documentDemo']['s3Region'],
            's3Bucket' => $params['documentDemo']['s3Bucket'],
            's3AccessKey' => $params['documentDemo']['s3AccessKey'],
            's3SecretKey' => $params['documentDemo']['s3SecretKey'],
            's3PathStyle' => $params['documentDemo']['s3PathStyle'],
        ],
    ],
    ConfiguredExtractor::class => [
        '__construct()' => [
            'extractorAdapter' => $params['documentDemo']['extractorAdapter'],
        ],
    ],
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
    DocumentProcessor::class => [
        '__construct()' => [
            'leaseSeconds' => $params['documentDemo']['leaseSeconds'],
        ],
    ],
    DocumentUploadService::class => [
        '__construct()' => [
            'maxFileBytes' => $params['documentDemo']['maxFileBytes'],
            'maxBatchBytes' => $params['documentDemo']['maxBatchBytes'],
            'allowedExtensions' => $params['documentDemo']['allowedExtensions'],
        ],
    ],
    HomePageAction::class => [
        '__construct()' => [
            'queueDriver' => $params['documentDemo']['queueDriver'],
        ],
    ],
    UploadAction::class => [
        '__construct()' => [
            'queueDriver' => $params['documentDemo']['queueDriver'],
        ],
    ],
    DocumentStorageInterface::class => static fn (DocumentStorageFactory $factory): DocumentStorageInterface => $factory->create(),
    ExtractorInterface::class => ConfiguredExtractor::class,
    SummarizerInterface::class => ConfiguredSummarizer::class,
    QueueInterface::class => static function (
        WorkerInterface $worker,
        LoopInterface $loop,
        LoggerInterface $logger,
        PushMiddlewareConfig $middlewareConfig,
        MessageSerializerInterface $serializer,
    ) use ($params): QueueInterface {
        $driver = $params['documentDemo']['queueDriver'];
        $queueName = $params['documentDemo']['queueName'];
        $createRedis = static function () use ($params): \Redis {
            $redis = new \Redis();
            $redis->connect(
                $params['documentDemo']['redisHost'],
                $params['documentDemo']['redisPort'],
                $params['documentDemo']['redisTimeout'],
            );

            return $redis;
        };

        $adapter = match ($driver) {
            'sync' => null,
            'amqp' => new AmqpAdapter(
                new AmqpQueueProvider(
                    new AMQPStreamConnection(
                        $params['documentDemo']['amqpHost'],
                        $params['documentDemo']['amqpPort'],
                        $params['documentDemo']['amqpUser'],
                        $params['documentDemo']['amqpPassword'],
                        $params['documentDemo']['amqpVhost'],
                    ),
                    new AmqpQueueSettings($queueName),
                ),
                $serializer,
                $loop,
            ),
            'redis' => new RedisAdapter(
                new RedisQueueProvider(
                    $createRedis(),
                    $queueName,
                ),
                $serializer,
                $loop,
            ),
            default => throw new \RuntimeException('QUEUE_DRIVER must be sync, amqp, or redis.'),
        };

        return new Queue($worker, $loop, $logger, $middlewareConfig, $adapter, $queueName);
    },
    QueueProviderInterface::class => static fn (QueueInterface $queue): QueueProviderInterface => new PredefinedQueueProvider([
        QueueProviderInterface::DEFAULT_QUEUE => $queue,
    ]),
    DocumentQueueInterface::class => ConfiguredDocumentQueue::class,
];
