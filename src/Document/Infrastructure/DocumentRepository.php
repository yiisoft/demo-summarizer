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
    public function __construct(
        private ConnectionInterface $db,
    ) {}

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
            'status' => DocumentStatus::UPLOADED,
            'progress' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();

        $document = $this->get((int) $this->db->getLastInsertId());
        $this->addEvent($document->id, 'uploaded', 'Document uploaded.', 0);
        return $document;
    }

    /**
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
     * @return list<Document>
     */
    public function queued(int $limit = 20): array
    {
        $rows = $this->db
            ->select('*')
            ->from('documents')
            ->where(['status' => DocumentStatus::QUEUED])
            ->orderBy(['id' => SORT_ASC])
            ->limit($limit)
            ->all();

        return $this->documentsFromRows($rows);
    }

    public function markQueued(int $id): void
    {
        $now = $this->now();
        $this->update($id, [
            'status' => DocumentStatus::QUEUED,
            'progress' => 5,
            'queued_at' => $now,
            'completed_at' => null,
            'failed_at' => null,
            'error' => null,
            'error_detail' => null,
            'updated_at' => $now,
        ]);
        $this->addEvent($id, 'queued', 'Document queued for processing.', 5);
    }

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
            'status' => DocumentStatus::EXTRACTING,
            'progress' => 20,
            'lease_until' => $leaseUntil,
            'started_at' => $startedAt,
            'completed_at' => null,
            'failed_at' => null,
            'error' => null,
            'error_detail' => null,
            'updated_at' => $startedAt,
        ]);
        $this->addEvent($id, 'extracting', 'Extraction started.', 20);

        return $this->get($id);
    }

    public function markSummarizing(int $id, string $markdownKey): void
    {
        $this->update($id, [
            'status' => DocumentStatus::SUMMARIZING,
            'progress' => 70,
            'markdown_key' => $markdownKey,
            'updated_at' => $this->now(),
        ]);
        $this->addEvent($id, 'summarizing', 'Markdown extracted. Summarization started.', 70);
    }

    public function complete(int $id, string $summary): void
    {
        $now = $this->now();
        $this->update($id, [
            'status' => DocumentStatus::COMPLETED,
            'progress' => 100,
            'summary' => $summary,
            'lease_until' => null,
            'completed_at' => $now,
            'updated_at' => $now,
        ]);
        $this->addEvent($id, 'completed', 'Processing completed.', 100);
    }

    public function fail(int $id, string $error, string $detail): void
    {
        $now = $this->now();
        $this->update($id, [
            'status' => DocumentStatus::FAILED,
            'progress' => 100,
            'lease_until' => null,
            'error' => $error,
            'error_detail' => $detail,
            'failed_at' => $now,
            'updated_at' => $now,
        ]);
        $this->addEvent($id, 'failed', $error, 100);
    }

    public function prepareRetry(int $id): void
    {
        $document = $this->get($id);
        $now = $this->now();
        $this->update($id, [
            'status' => DocumentStatus::UPLOADED,
            'progress' => 0,
            'lease_until' => null,
            'summary' => null,
            'error' => null,
            'error_detail' => null,
            'retry_count' => $document->retryCount + 1,
            'updated_at' => $now,
        ]);
        $this->addEvent($id, 'retry', 'Manual retry requested.', 0);
    }

    public function delete(int $id): void
    {
        $this->db->createCommand()->delete('documents', ['id' => $id])->execute();
    }

    public function deleteAll(): void
    {
        $this->db->createCommand()->delete('processing_events')->execute();
        $this->db->createCommand()->delete('documents')->execute();
    }

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
     * @param array<string, int|string|null> $values
     */
    private function update(int $id, array $values): void
    {
        $this->db->createCommand()->update('documents', $values, ['id' => $id])->execute();
    }

    private function now(): string
    {
        return $this->format(new DateTimeImmutable());
    }

    private function format(DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * @param array<array-key, mixed> $rows
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
     * @param array<array-key, mixed> $rows
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
