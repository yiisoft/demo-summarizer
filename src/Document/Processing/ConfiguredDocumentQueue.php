<?php

declare(strict_types=1);

namespace App\Document\Processing;

use App\Document\Infrastructure\DocumentRepository;
use Yiisoft\Queue\QueueInterface;

/**
 * Marks documents queued and pushes processing messages into the configured Yii queue.
 */
final readonly class ConfiguredDocumentQueue implements DocumentQueueInterface
{
    public function __construct(
        private DocumentRepository $repository,
        private QueueInterface $queue,
    ) {}

    public function enqueue(int $documentId): void
    {
        $this->repository->markQueued($documentId);
        $this->queue->push(new DocumentMessage($documentId));
    }
}
