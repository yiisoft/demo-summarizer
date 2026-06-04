<?php

declare(strict_types=1);

use App\Web\Document\UploadAction;
use App\Web\HomePage\Action as HomePageAction;

/** @var array $params */

return [
    HomePageAction::class => [
        '__construct()' => [
            'queueDriver' => $params['documentDemo']['queueDriver'],
            'workers' => $params['documentDemo']['workers'],
            'extractorAdapter' => $params['documentDemo']['extractorAdapter'],
            'llmAdapter' => $params['documentDemo']['llmAdapter'],
            'llmModel' => $params['documentDemo']['llmModel'],
            'storageDriver' => $params['documentDemo']['storageDriver'],
        ],
    ],
    UploadAction::class => [
        '__construct()' => [
            'queueDriver' => $params['documentDemo']['queueDriver'],
            'workers' => $params['documentDemo']['workers'],
            'extractorAdapter' => $params['documentDemo']['extractorAdapter'],
            'llmAdapter' => $params['documentDemo']['llmAdapter'],
            'llmModel' => $params['documentDemo']['llmModel'],
            'storageDriver' => $params['documentDemo']['storageDriver'],
        ],
    ],
];
