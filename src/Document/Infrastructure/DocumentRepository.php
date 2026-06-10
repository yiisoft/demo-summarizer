<?php

declare(strict_types=1);

namespace App\Document\Infrastructure;

use App\Document\Domain\Document;
use App\Document\Domain\DocumentEvent;
use App\Document\Domain\DocumentStatus;
use DateInterval;
use DateTimeImmutable;
use UnexpectedValueException;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * Persists document records, status transitions, and processing timeline events.
 */
final readonly class DocumentRepository
{
    /**
     * @param ConnectionInterface $db Database connection.
     */
    public function __construct(
        private ConnectionInterface $db,
    ) {}

    /**
     * Creates a document record in uploaded state.
     *
     * @param non-empty-string $originalName Original client filename.
     * @param non-empty-string $storageKey Storage key for the original file.
     * @param non-empty-string $mimeType Client MIME type.
     * @param non-empty-string $extension Lowercase file extension.
     * @param int $byteSize Uploaded file size in bytes.
     */
    public function create(
        string $originalName,
        string $storageKey,
        string $mimeType,
        string $extension,
        int $byteSize,
    ): Document {
        $now = $this->now();
        $this->db->createCommand()->insert('documents', [
            'original_name' => $originalName,
            'storage_key' => $storageKey,
            'mime_type' => $mimeType,
            'extension' => $extension,
            'byte_size' => $byteSize,
            'status' => DocumentStatus::UPLOADED->value,
            'progress' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();

        $document = $this->get((int) $this->db->getLastInsertId());
        $this->addEvent($document->id, 'uploaded', 'Document uploaded.', 0);
        return $document;
    }

    /**
     * Returns all documents ordered for the dashboard.
     *
     * @return list<Document>
     */
    public function all(): array
    {
        $rows = $this->db
            ->select('*')
            ->from('documents')
            ->orderBy(['id' => SORT_DESC])
            ->all();

        return $this->documentsFromRows($rows);
    }

    /**
     * Returns a document by identifier.
     *
     * @param int $id Document identifier.
     */
    public function get(int $id): Document
    {
        $row = $this->db
            ->select('*')
            ->from('documents')
            ->where(['id' => $id])
            ->one();

        if (!is_array($row)) {
            throw new DocumentNotFoundException($id);
        }

        /** @var array<string, mixed> $row */
        return $this->documentFromRow($row);
    }

    /**
     * Returns timeline events for a document.
     *
     * @param int $documentId Document identifier.
     *
     * @return list<DocumentEvent>
     */
    public function events(int $documentId): array
    {
        $rows = $this->db
            ->select('*')
            ->from('processing_events')
            ->where(['document_id' => $documentId])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        return $this->eventsFromRows($rows);
    }

    /**
     * Marks a document as queued for processing.
     *
     * @param int $id Document identifier.
     */
    public function markQueued(int $id): void
    {
        $now = $this->now();
        $this->update($id, [
            'status' => DocumentStatus::QUEUED->value,
            'progress' => 5,
            'error' => null,
            'updated_at' => $now,
        ]);
        $this->addEvent($id, 'queued', 'Document queued for processing.', 5);
    }

    /**
     * Claims a document for processing if it is available.
     *
     * @param int $id Document identifier.
     * @param int $leaseSeconds Processing lease duration in seconds.
     */
    public function claim(int $id, int $leaseSeconds): ?Document
    {
        $now = new DateTimeImmutable();
        $startedAt = $this->format($now);
        $leaseUntil = $this->format($now->add(new DateInterval('PT' . $leaseSeconds . 'S')));

        $affectedRows = $this->db->createCommand()->update(
            'documents',
            [
                'status' => DocumentStatus::EXTRACTING->value,
                'progress' => 20,
                'lease_until' => $leaseUntil,
                'error' => null,
                'updated_at' => $startedAt,
            ],
            [
                'and',
                ['id' => $id],
                ['status' => [DocumentStatus::QUEUED->value, DocumentStatus::FAILED->value]],
                [
                    'or',
                    ['lease_until' => null],
                    ['<=', 'lease_until', $startedAt],
                ],
            ],
        )->execute();

        if ($affectedRows === 0) {
            $this->get($id);
            return null;
        }

        $this->addEvent($id, 'extracting', 'Extraction started.', 20);

        return $this->get($id);
    }

    /**
     * Marks a document as summarizing after markdown extraction.
     *
     * @param int $id Document identifier.
     * @param non-empty-string $markdownKey Storage key for extracted markdown.
     */
    public function markSummarizing(int $id, string $markdownKey): void
    {
        $this->update($id, [
            'status' => DocumentStatus::SUMMARIZING->value,
            'progress' => 70,
            'markdown_key' => $markdownKey,
            'updated_at' => $this->now(),
        ]);
        $this->addEvent($id, 'summarizing', 'Markdown extracted. Summarization started.', 70);
    }

    /**
     * Marks a document as completed with its generated summary.
     *
     * @param int $id Document identifier.
     * @param string $summary Generated summary text.
     */
    public function complete(int $id, string $summary): void
    {
        $now = $this->now();
        $this->update($id, [
            'status' => DocumentStatus::COMPLETED->value,
            'progress' => 100,
            'summary' => $summary,
            'lease_until' => null,
            'updated_at' => $now,
        ]);
        $this->addEvent($id, 'completed', 'Processing completed.', 100);
    }

    /**
     * Marks a document as failed.
     *
     * @param int $id Document identifier.
     * @param string $error User-facing error message.
     */
    public function fail(int $id, string $error): void
    {
        $error = $error === '' ? 'Processing failed.' : $error;
        $now = $this->now();
        $this->update($id, [
            'status' => DocumentStatus::FAILED->value,
            'progress' => 100,
            'lease_until' => null,
            'error' => $error,
            'updated_at' => $now,
        ]);
        $this->addEvent($id, 'failed', $error, 100);
    }

    /**
     * Resets a failed document for manual retry.
     *
     * @param int $id Document identifier.
     */
    public function prepareRetry(int $id): void
    {
        $document = $this->get($id);
        $now = $this->now();
        $this->update($id, [
            'status' => DocumentStatus::UPLOADED->value,
            'progress' => 0,
            'lease_until' => null,
            'summary' => null,
            'error' => null,
            'retry_count' => $document->retryCount + 1,
            'updated_at' => $now,
        ]);
        $this->addEvent($id, 'retry', 'Manual retry requested.', 0);
    }

    /**
     * Deletes a document record.
     *
     * @param int $id Document identifier.
     */
    public function delete(int $id): void
    {
        $this->db->createCommand()->delete('documents', ['id' => $id])->execute();
    }

    /**
     * Deletes all document records and timeline events.
     */
    public function deleteAll(): void
    {
        $this->db->createCommand()->delete('processing_events')->execute();
        $this->db->createCommand()->delete('documents')->execute();
    }

    /**
     * Adds a document timeline event.
     *
     * @param int $documentId Document identifier.
     * @param non-empty-string $type Event type.
     * @param non-empty-string $message User-facing event message.
     * @param int $progress Progress percentage.
     */
    public function addEvent(int $documentId, string $type, string $message, int $progress): void
    {
        $this->db->createCommand()->insert('processing_events', [
            'document_id' => $documentId,
            'event_type' => $type,
            'message' => $message,
            'progress' => $progress,
            'created_at' => $this->now(),
        ])->execute();
    }

    /**
     * Updates a document row.
     *
     * @param int $id Document identifier.
     * @param array<string, int|string|null> $values Column values.
     */
    private function update(int $id, array $values): void
    {
        $this->db->createCommand()->update('documents', $values, ['id' => $id])->execute();
    }

    /**
     * Returns the current timestamp in database format.
     *
     * @return non-empty-string
     */
    private function now(): string
    {
        return $this->format(new DateTimeImmutable());
    }

    /**
     * Formats a timestamp for database storage.
     *
     * @param DateTimeImmutable $date Timestamp to format.
     *
     * @return non-empty-string
     */
    private function format(DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Hydrates document rows.
     *
     * @param array<array-key, mixed> $rows Raw database rows.
     *
     * @return list<Document>
     */
    private function documentsFromRows(array $rows): array
    {
        $documents = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new UnexpectedValueException('Document row must be an array.');
            }

            /** @var array<string, mixed> $row */
            $documents[] = $this->documentFromRow($row);
        }

        return $documents;
    }

    /**
     * Hydrates a document from a database row.
     *
     * @param array<string, mixed> $row
     */
    private function documentFromRow(array $row): Document
    {
        return new Document(
            (int) $row['id'],
            $this->nonEmptyString($row['original_name'], 'original_name'),
            $this->nonEmptyString($row['storage_key'], 'storage_key'),
            $this->nonEmptyString($row['mime_type'], 'mime_type'),
            $this->nonEmptyString($row['extension'], 'extension'),
            (int) $row['byte_size'],
            DocumentStatus::from((string) $row['status']),
            (int) $row['progress'],
            $row['lease_until'] === null ? null : $this->nonEmptyString($row['lease_until'], 'lease_until'),
            $row['markdown_key'] === null ? null : $this->nonEmptyString($row['markdown_key'], 'markdown_key'),
            $row['summary'] === null ? null : (string) $row['summary'],
            $row['error'] === null ? null : (string) $row['error'],
            (int) $row['retry_count'],
            $this->nonEmptyString($row['updated_at'], 'updated_at'),
        );
    }

    /**
     * Hydrates timeline event rows.
     *
     * @param array<array-key, mixed> $rows Raw database rows.
     *
     * @return list<DocumentEvent>
     */
    private function eventsFromRows(array $rows): array
    {
        $events = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new UnexpectedValueException('Document event row must be an array.');
            }

            /** @var array<string, mixed> $row */
            $events[] = $this->eventFromRow($row);
        }

        return $events;
    }

    /**
     * Hydrates a timeline event from a database row.
     *
     * @param array<string, mixed> $row
     */
    private function eventFromRow(array $row): DocumentEvent
    {
        return new DocumentEvent(
            (int) $row['id'],
            (int) $row['document_id'],
            $this->nonEmptyString($row['event_type'], 'event_type'),
            $this->nonEmptyString($row['message'], 'message'),
            (int) $row['progress'],
            $this->nonEmptyString($row['created_at'], 'created_at'),
        );
    }

    /**
     * Reads a required non-empty string column from a database row.
     *
     * @return non-empty-string
     */
    private function nonEmptyString(mixed $value, string $column): string
    {
        $value = (string) $value;
        if ($value === '') {
            throw new UnexpectedValueException("Database column \"$column\" must be a non-empty string.");
        }

        return $value;
    }
}
