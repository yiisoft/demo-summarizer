<?php

declare(strict_types=1);

/**
 * @var Closure(non-empty-string, string): string $env
 * @var string $root
 */

return [
    'databaseDsn' => $env('DATABASE_DSN', 'sqlite:' . $root . '/runtime/documents.sqlite'),
];
