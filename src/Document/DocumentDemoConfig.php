<?php

declare(strict_types=1);

namespace App\Document;

final readonly class DocumentDemoConfig
{
    /**
     * @param list<string> $allowedExtensions
     */
    public function __construct(
        public string $queueDriver,
        public string $databaseDsn,
        public string $storageDriver,
        public string $localStorageRoot,
        public string $s3Endpoint,
        public string $s3Region,
        public string $s3Bucket,
        public string $s3AccessKey,
        public string $s3SecretKey,
        public bool $s3PathStyle,
        public int $leaseSeconds,
        public string $extractorAdapter,
        public string $llmAdapter,
        public string $ollamaBaseUrl,
        public string $ollamaModel,
        public int $maxFileBytes,
        public int $maxBatchBytes,
        public array $allowedExtensions,
    ) {}
}
