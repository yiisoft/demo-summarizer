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
     * @param string $originalName Original client filename.
     * @param string $storageKey Storage key for the original file.
     * @param string $mimeType Client MIME type.
     * @param string $extension Lowercase file extension.
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
        return Document::fromRow($row);
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
        $document = $this->get($id);
        $now = new DateTimeImmutable();
        if ($document->leaseUntil !== null && new DateTimeImmutable($document->leaseUntil) > $now) {
            return null;
        }

        if (!in_array($document->status, [DocumentStatus::QUEUED, DocumentStatus::FAILED], true)) {
            return null;
        }

        $startedAt = $this->format($now);
        $leaseUntil = $this->format($now->add(new DateInterval('PT' . $leaseSeconds . 'S')));
        $this->update($id, [
            'status' => DocumentStatus::EXTRACTING->value,
            'progress' => 20,
            'lease_until' => $leaseUntil,
            'error' => null,
            'updated_at' => $startedAt,
        ]);
        $this->addEvent($id, 'extracting', 'Extraction started.', 20);

        return $this->get($id);
    }

    /**
     * Marks a document as summarizing after markdown extraction.
     *
     * @param int $id Document identifier.
     * @param string $markdownKey Storage key for extracted markdown.
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
     * @param string $type Event type.
     * @param string $message User-facing event message.
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
     */
    private function now(): string
    {
        return $this->format(new DateTimeImmutable());
    }

    /**
     * Formats a timestamp for database storage.
     *
     * @param DateTimeImmutable $date Timestamp to format.
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
            $documents[] = Document::fromRow($row);
        }

        return $documents;
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
            $events[] = DocumentEvent::fromRow($row);
        }

        return $events;
    }
}
