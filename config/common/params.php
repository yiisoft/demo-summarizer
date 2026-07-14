<?php

declare(strict_types=1);

use App\Shared\ApplicationParams;
use App\Document\Processing\SummarizeDocumentMessage;
use App\Document\Processing\SummarizeDocumentMessageHandler;
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
        'messages' => [
            SummarizeDocumentMessage::class => SummarizeDocumentMessage::class,
        ],
        'handlers' => [
            SummarizeDocumentMessage::class => SummarizeDocumentMessageHandler::class,
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

    'documentDemo' => array_merge(
        require __DIR__ . '/db.php',
        require __DIR__ . '/extraction.php',
        require __DIR__ . '/processing.php',
        require __DIR__ . '/queue.php',
        require __DIR__ . '/storage.php',
        require __DIR__ . '/summarization.php',
    ),
];
