<?php

declare(strict_types=1);

namespace App\Document\Domain;

/**
 * Represents a single progress timeline entry for a document.
 */
final readonly class DocumentEvent
{
    /**
     * @param int $id Database identifier.
     * @param int $documentId Related document identifier.
     * @param non-empty-string $type Event type.
     * @param non-empty-string $message User-facing event message.
     * @param int $progress Progress percentage recorded with the event.
     * @param non-empty-string $createdAt Creation timestamp.
     */
    public function __construct(
        public int $id,
        public int $documentId,
        public string $type,
        public string $message,
        public int $progress,
        public string $createdAt,
    ) {}
}
