<?php

declare(strict_types=1);

use App\Document\Processing\DocumentProcessor;
use App\Document\Processing\DocumentUploadService;

/** @var array $params */

return [
    DocumentProcessor::class => [
        '__construct()' => [
            'leaseSeconds' => $params['documentDemo']['leaseSeconds'],
        ],
    ],
    DocumentUploadService::class => [
        '__construct()' => [
            'maxFiles' => $params['documentDemo']['maxFiles'],
            'maxFileBytes' => $params['documentDemo']['maxFileBytes'],
            'maxBatchBytes' => $params['documentDemo']['maxBatchBytes'],
            'allowedExtensions' => $params['documentDemo']['allowedExtensions'],
            'allowedMimeTypes' => $params['documentDemo']['allowedMimeTypes'],
        ],
    ],
];
