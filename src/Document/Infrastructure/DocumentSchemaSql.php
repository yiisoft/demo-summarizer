<?php

declare(strict_types=1);

namespace App\Document\Infrastructure;

/**
 * Contains SQL statements for the document workflow schema.
 */
final class DocumentSchemaSql
{
    public const CREATE_DOCUMENTS = <<<'SQL'
CREATE TABLE IF NOT EXISTS documents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    original_name TEXT NOT NULL,
    storage_key TEXT NOT NULL,
    mime_type TEXT NOT NULL,
    extension TEXT NOT NULL,
    byte_size INTEGER NOT NULL,
    status TEXT NOT NULL,
    progress INTEGER NOT NULL DEFAULT 0,
    lease_until TEXT NULL,
    markdown_key TEXT NULL,
    summary TEXT NULL,
    error TEXT NULL,
    error_detail TEXT NULL,
    retry_count INTEGER NOT NULL DEFAULT 0,
    queued_at TEXT NULL,
    started_at TEXT NULL,
    completed_at TEXT NULL,
    failed_at TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
)
SQL;

    public const CREATE_PROCESSING_EVENTS = <<<'SQL'
CREATE TABLE IF NOT EXISTS processing_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    document_id INTEGER NOT NULL,
    event_type TEXT NOT NULL,
    message TEXT NOT NULL,
    progress INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    FOREIGN KEY(document_id) REFERENCES documents(id) ON DELETE CASCADE
)
SQL;

    public const CREATE_DOCUMENTS_STATUS_INDEX = 'CREATE INDEX IF NOT EXISTS idx_documents_status ON documents(status)';

    public const CREATE_PROCESSING_EVENTS_DOCUMENT_INDEX = 'CREATE INDEX IF NOT EXISTS idx_processing_events_document ON processing_events(document_id, id)';

    public const DROP_PROCESSING_EVENTS_DOCUMENT_INDEX = 'DROP INDEX IF EXISTS idx_processing_events_document';

    public const DROP_DOCUMENTS_STATUS_INDEX = 'DROP INDEX IF EXISTS idx_documents_status';

    public const DROP_PROCESSING_EVENTS = 'DROP TABLE IF EXISTS processing_events';

    public const DROP_DOCUMENTS = 'DROP TABLE IF EXISTS documents';
}
