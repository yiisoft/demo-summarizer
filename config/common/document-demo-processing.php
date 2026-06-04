<?php

declare(strict_types=1);

/**
 * @var Closure(non-empty-string, string): string $env
 */

return [
    'leaseSeconds' => (int) $env('DOCUMENT_PROCESSING_LEASE_SECONDS', '900'),
    'maxFileBytes' => 20 * 1024 * 1024,
    'maxBatchBytes' => 100 * 1024 * 1024,
    'allowedExtensions' => ['md', 'txt', 'html', 'pdf', 'docx'],
];
