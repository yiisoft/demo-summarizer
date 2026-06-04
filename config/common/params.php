<?php

declare(strict_types=1);

use App\Shared\ApplicationParams;
use App\Document\Processing\DocumentMessage;
use App\Document\Processing\DocumentMessageHandler;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\AssetManager;
use Yiisoft\Definitions\Reference;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Yii\View\Renderer\CsrfViewInjection;

$root = dirname(__DIR__, 2);

/**
 * @param non-empty-string $key
 */
$env = static fn (string $key, string $default): string => getenv($key) === false ? $default : (string) getenv($key);

return [
    'application' => require __DIR__ . '/application.php',

    'yiisoft/aliases' => [
        'aliases' => require __DIR__ . '/aliases.php',
    ],

    'yiisoft/view' => [
        'basePath' => null,
        'parameters' => [
            'assetManager' => Reference::to(AssetManager::class),
            'applicationParams' => Reference::to(ApplicationParams::class),
            'aliases' => Reference::to(Aliases::class),
            'urlGenerator' => Reference::to(UrlGeneratorInterface::class),
            'currentRoute' => Reference::to(CurrentRoute::class),
        ],
    ],

    'yiisoft/yii-view-renderer' => [
        'viewPath' => null,
        'layout' => '@src/Web/Shared/Layout/Main/layout.php',
        'injections' => [
            Reference::to(CsrfViewInjection::class),
        ],
    ],

    'yiisoft/queue' => [
        'handlers' => [
            DocumentMessage::class => DocumentMessageHandler::class,
        ],
        'middlewares-push' => [],
        'middlewares-consume' => [],
        'middlewares-fail' => [],
    ],

    'yiisoft/db-migration' => [
        'newMigrationNamespace' => 'App\\Document\\Migration',
        'newMigrationPath' => '',
        'sourceNamespaces' => [
            'App\\Document\\Migration',
        ],
        'sourcePaths' => [],
    ],

    'documentDemo' => [
        'queueDriver' => $env('QUEUE_DRIVER', 'sync'),
        'queueName' => $env('QUEUE_NAME', 'document-demo'),
        'amqpHost' => $env('AMQP_HOST', 'rabbitmq'),
        'amqpPort' => (int) $env('AMQP_PORT', '5672'),
        'amqpUser' => $env('AMQP_USER', 'guest'),
        'amqpPassword' => $env('AMQP_PASSWORD', 'guest'),
        'amqpVhost' => $env('AMQP_VHOST', '/'),
        'redisHost' => $env('REDIS_HOST', 'valkey'),
        'redisPort' => (int) $env('REDIS_PORT', '6379'),
        'redisTimeout' => (int) $env('REDIS_TIMEOUT', '3'),
        'databaseDsn' => $env('DATABASE_DSN', 'sqlite:' . $root . '/runtime/documents.sqlite'),
        'storageDriver' => $env('DOCUMENT_STORAGE_DRIVER', 's3'),
        'localStorageRoot' => $env('DOCUMENT_LOCAL_STORAGE_ROOT', $root . '/runtime/document-objects'),
        's3Endpoint' => $env('S3_ENDPOINT', 'http://garage:3900'),
        's3Region' => $env('S3_REGION', 'garage'),
        's3Bucket' => $env('S3_BUCKET', 'documents'),
        's3AccessKey' => $env('S3_ACCESS_KEY', 'GKdemo000000000000000000000000000000'),
        's3SecretKey' => $env('S3_SECRET_KEY', 'garage-demo-secret-key-000000000000000000000000000000'),
        's3PathStyle' => filter_var($env('S3_PATH_STYLE', 'true'), FILTER_VALIDATE_BOOLEAN),
        'leaseSeconds' => (int) $env('DOCUMENT_PROCESSING_LEASE_SECONDS', '900'),
        'extractorAdapter' => $env('EXTRACTOR_ADAPTER', 'kreuzberg'),
        'llmAdapter' => $env('LLM_ADAPTER', 'mock'),
        'ollamaBaseUrl' => $env('OLLAMA_BASE_URL', 'http://ollama:11434'),
        'ollamaModel' => $env('OLLAMA_MODEL', 'llama3.2'),
        'llmProvider' => $env('LLM_PROVIDER', ''),
        'llmApiKey' => $env('LLM_API_KEY', ''),
        'llmModel' => $env('LLM_MODEL', ''),
        'maxFileBytes' => 20 * 1024 * 1024,
        'maxBatchBytes' => 100 * 1024 * 1024,
        'allowedExtensions' => ['md', 'txt', 'html', 'pdf', 'docx'],
    ],
];
