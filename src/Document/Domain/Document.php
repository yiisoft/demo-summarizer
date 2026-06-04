<?php

declare(strict_types=1);

namespace App\Document\Domain;

final readonly class Document
{
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

    public function isActive(): bool
    {
        return in_array($this->status, DocumentStatus::ACTIVE, true);
    }
}
