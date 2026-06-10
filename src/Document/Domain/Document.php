<?php

declare(strict_types=1);

namespace App\Document\Domain;

/**
 * Represents a document row and its processing state.
 */
final readonly class Document
{
    /**
     * @param int $id Database identifier.
     * @param string $originalName Original client filename.
     * @param string $storageKey Storage key for the original file.
     * @param string $mimeType Client MIME type.
     * @param string $extension Lowercase file extension.
     * @param int $byteSize Uploaded file size in bytes.
     * @param DocumentStatus $status Current processing status.
     * @param int $progress Current processing progress percentage.
     * @param string|null $leaseUntil Timestamp until which a worker owns processing for this document; other workers skip it while the lease is active.
     * @param string|null $markdownKey Storage object key for markdown extracted from the original document; null until extraction succeeds.
     * @param string|null $summary Generated summary text.
     * @param string|null $error User-facing processing error.
     * @param int $retryCount Manual retry count.
     * @param string $updatedAt Last update timestamp.
     */
    public function __construct(
        public int $id,
        public string $originalName,
        public string $storageKey,
        public string $mimeType,
        public string $extension,
        public int $byteSize,
        public DocumentStatus $status,
        public int $progress,
        public ?string $leaseUntil,
        public ?string $markdownKey,
        public ?string $summary,
        public ?string $error,
        public int $retryCount,
        public string $updatedAt,
    ) {}

    /**
     * Returns whether the document is in an active processing status.
     */
    public function isActive(): bool
    {
        return in_array($this->status, DocumentStatus::ACTIVE, true);
    }
}
