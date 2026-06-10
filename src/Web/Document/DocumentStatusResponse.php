<?php

declare(strict_types=1);

namespace App\Web\Document;

/**
 * Response payload for the document status endpoint.
 */
final readonly class DocumentStatusResponse
{
    /**
     * @param non-empty-string $status Processing status value.
     * @param non-empty-string $updatedAt Last update timestamp.
     */
    public function __construct(
        public int $id,
        public string $status,
        public int $progress,
        public ?string $summary,
        public ?string $error,
        public string $updatedAt,
    ) {}
}
