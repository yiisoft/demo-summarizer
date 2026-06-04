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
     * @param string $status Current processing status.
     * @param int $progress Current processing progress percentage.
     * @param string|null $leaseUntil Timestamp until which a worker owns processing for this document; other workers skip it while the lease is active.
     * @param string|null $markdownKey Storage object key for markdown extracted from the original document; null until extraction succeeds.
     * @param string|null $summary Generated summary text.
     * @param string|null $error User-facing processing error.
     * @param string|null $errorDetail Internal processing error detail.
     * @param int $retryCount Manual retry count.
     * @param string|null $queuedAt Queue timestamp.
     * @param string|null $startedAt Processing start timestamp.
     * @param string|null $completedAt Completion timestamp.
     * @param string|null $failedAt Failure timestamp.
     * @param string $createdAt Creation timestamp.
     * @param string $updatedAt Last update timestamp.
     */
    public function __construct(
        public int $id,
        public string $originalName,
        public string $storageKey,
        public string $mimeType,
        public string $extension,
        public int $byteSize,
        public string $status,
        public int $progress,
        public ?string $leaseUntil,
        public ?string $markdownKey,
        public ?string $summary,
        public ?string $error,
        public ?string $errorDetail,
        public int $retryCount,
        public ?string $queuedAt,
        public ?string $startedAt,
        public ?string $completedAt,
        public ?string $failedAt,
        public string $createdAt,
        public string $updatedAt,
    ) {}

    /**
     * Hydrates a document from a database row.
     *
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['original_name'],
            (string) $row['storage_key'],
            (string) $row['mime_type'],
            (string) $row['extension'],
            (int) $row['byte_size'],
            (string) $row['status'],
            (int) $row['progress'],
            $row['lease_until'] === null ? null : (string) $row['lease_until'],
            $row['markdown_key'] === null ? null : (string) $row['markdown_key'],
            $row['summary'] === null ? null : (string) $row['summary'],
            $row['error'] === null ? null : (string) $row['error'],
            $row['error_detail'] === null ? null : (string) $row['error_detail'],
            (int) $row['retry_count'],
            $row['queued_at'] === null ? null : (string) $row['queued_at'],
            $row['started_at'] === null ? null : (string) $row['started_at'],
            $row['completed_at'] === null ? null : (string) $row['completed_at'],
            $row['failed_at'] === null ? null : (string) $row['failed_at'],
            (string) $row['created_at'],
            (string) $row['updated_at'],
        );
    }

    /**
     * Returns whether the document is in an active processing status.
     */
    public function isActive(): bool
    {
        return in_array($this->status, DocumentStatus::ACTIVE, true);
    }
}
