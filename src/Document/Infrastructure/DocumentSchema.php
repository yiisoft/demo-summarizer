<?php

declare(strict_types=1);

namespace App\Document\Infrastructure;

final readonly class DocumentSchema
{
    public function __construct(
        private DocumentDatabase $database,
    ) {}

    public function migrate(): void
    {
        $pdo = $this->database->pdo();
        $pdo->exec(DocumentSchemaSql::CREATE_DOCUMENTS);
        $pdo->exec(DocumentSchemaSql::CREATE_PROCESSING_EVENTS);
        $pdo->exec(DocumentSchemaSql::CREATE_DOCUMENTS_STATUS_INDEX);
        $pdo->exec(DocumentSchemaSql::CREATE_PROCESSING_EVENTS_DOCUMENT_INDEX);
    }
}
