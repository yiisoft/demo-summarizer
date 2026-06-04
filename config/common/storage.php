<?php

declare(strict_types=1);

/**
 * @var Closure(non-empty-string, string): string $env
 * @var string $root
 */

return [
    'storageDriver' => $env('DOCUMENT_STORAGE_DRIVER', 's3'),
    'localStorageRoot' => $env('DOCUMENT_LOCAL_STORAGE_ROOT', $root . '/runtime/document-objects'),
    's3Endpoint' => $env('S3_ENDPOINT', 'http://garage:3900'),
    's3Region' => $env('S3_REGION', 'garage'),
    's3Bucket' => $env('S3_BUCKET', 'documents'),
    's3AccessKey' => $env('S3_ACCESS_KEY', 'GKdemo000000000000000000000000000000'),
    's3SecretKey' => $env('S3_SECRET_KEY', 'garage-demo-secret-key-000000000000000000000000000000'),
    's3PathStyle' => filter_var($env('S3_PATH_STYLE', 'true'), FILTER_VALIDATE_BOOLEAN),
];
