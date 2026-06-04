<?php

declare(strict_types=1);

use App\Document\Extraction\ConfiguredExtractor;
use App\Document\Extraction\ExtractorInterface;

/** @var array $params */

return [
    ConfiguredExtractor::class => [
        '__construct()' => [
            'extractorAdapter' => $params['documentDemo']['extractorAdapter'],
        ],
    ],
    ExtractorInterface::class => ConfiguredExtractor::class,
];
