<?php

declare(strict_types=1);

/**
 * @var Closure(non-empty-string, string): string $env
 */

return [
    'leaseSeconds' => (int) $env('DOCUMENT_PROCESSING_LEASE_SECONDS', '900'),
    'maxFiles' => 20,
    'maxFileBytes' => 50 * 1024 * 1024,
    'maxBatchBytes' => 20 * 50 * 1024 * 1024,
    'allowedExtensions' => ['md', 'txt', 'html', 'pdf', 'docx'],
    'allowedMimeTypes' => [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip',
        'text/html',
        'text/markdown',
        'text/plain',
        'text/x-markdown',
    ],
];
