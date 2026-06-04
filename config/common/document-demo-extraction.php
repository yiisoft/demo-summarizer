<?php

declare(strict_types=1);

/**
 * @var Closure(non-empty-string, string): string $env
 */

return [
    'extractorAdapter' => $env('EXTRACTOR_ADAPTER', 'kreuzberg'),
];
