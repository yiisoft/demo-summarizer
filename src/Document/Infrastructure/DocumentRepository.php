<?php

declare(strict_types=1);

namespace App\Document\Infrastructure;

use App\Document\Domain\Document;
use App\Document\Domain\DocumentEvent;
use App\Document\Domain\DocumentStatus;
use DateInterval;
use DateTimeImmutable;
use PDO;
use UnexpectedValueException;

final readonly class DocumentRepository
{
    public function __construct(
        private DocumentDatabase $database,
        private DocumentSchema $schema,
    ) {}

    public function create(
        string $originalName,
        string $storageKey,
        string $mimeType,
        string $extension,
        int $byteSize,
    ): Document {
        $this->schema->migrate();
        $now = $this->now();
        $pdo = $this->database->pdo();
        $statement = $pdo->prepare(
            <<<'SQL'
INSERT INTO documents (
    original_name, storage_key, mime_type, extension, byte_size, status, progress, created_at, updated_at
) VALUES (
    :original_name, :storage_key, :mime_type, :extension, :byte_size, :status, 0, :created_at, :updated_at
)
SQL
        );
        $statement->execute([
            'original_name' => $originalName,
            'storage_key' => $storageKey,
            'mime_type' => $mimeType,
            'extension' => $extension,
            'byte_size' => $byteSize,
            'status' => DocumentStatus::UPLOADED,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $document = $this->get((int) $pdo->lastInsertId());
        $this->addEvent($document->id, 'uploaded', 'Document uploaded.', 0);
        return $document;
    }

    /**
     * @return list<Document>
     */
    public function all(): array
    {
        $this->schema->migrate();
        $rows = $this->database->pdo()
            ->query('SELECT * FROM documents ORDER BY id DESC')
            ->fetchAll();

        return $this->documentsFromRows($rows);
    }

    public function get(int $id): Document
    {
        $this->schema->migrate();
        $statement = $this->database->pdo()->prepare('SELECT * FROM documents WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

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
        $this->schema->migrate();
        $statement = $this->database->pdo()->prepare(
            'SELECT * FROM processing_events WHERE document_id = :document_id ORDER BY id ASC',
        );
        $statement->execute(['document_id' => $documentId]);

        return $this->eventsFromRows($statement->fetchAll());
    }

    /**
     * @return list<Document>
     */
    public function queued(int $limit = 20): array
    {
        $this->schema->migrate();
        $statement = $this->database->pdo()->prepare(
            'SELECT * FROM documents WHERE status = :status ORDER BY id ASC LIMIT :limit',
        );
        $statement->bindValue('status', DocumentStatus::QUEUED);
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $this->documentsFromRows($statement->fetchAll());
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
        $this->schema->migrate();
        $statement = $this->database->pdo()->prepare('DELETE FROM documents WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function addEvent(int $documentId, string $type, string $message, int $progress): void
    {
        $this->schema->migrate();
        $statement = $this->database->pdo()->prepare(
            <<<'SQL'
INSERT INTO processing_events (document_id, event_type, message, progress, created_at)
VALUES (:document_id, :event_type, :message, :progress, :created_at)
SQL
        );
        $statement->execute([
            'document_id' => $documentId,
            'event_type' => $type,
            'message' => $message,
            'progress' => $progress,
            'created_at' => $this->now(),
        ]);
    }

    /**
     * @param array<string, int|string|null> $values
     */
    private function update(int $id, array $values): void
    {
        $assignments = [];
        foreach ($values as $column => $_) {
            $assignments[] = $column . ' = :' . $column;
        }

        $statement = $this->database->pdo()->prepare(
            'UPDATE documents SET ' . implode(', ', $assignments) . ' WHERE id = :id',
        );
        $statement->execute($values + ['id' => $id]);
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
