<?php

declare(strict_types=1);

namespace App\Document\Domain;

/**
 * Represents a single progress timeline entry for a document.
 */
final readonly class DocumentEvent
{
    public function __construct(
        public int $id,
        public int $documentId,
        public string $type,
        public string $message,
        public int $progress,
        public string $createdAt,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['document_id'],
            (string) $row['event_type'],
            (string) $row['message'],
            (int) $row['progress'],
            (string) $row['created_at'],
        );
    }
}
