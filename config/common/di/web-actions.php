<?php

declare(strict_types=1);

use App\Web\Document\UploadAction;
use App\Web\HomePage\Action as HomePageAction;

/** @var array $params */

return [
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
];
