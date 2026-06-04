<?php

declare(strict_types=1);

namespace App\Document\Processing;

use App\Document\Infrastructure\DocumentRepository;
use Yiisoft\Queue\QueueInterface;

final readonly class ConfiguredDocumentQueue implements DocumentQueueInterface
{
    public function __construct(
        private string $queueDriver,
        private DocumentRepository $repository,
        private QueueInterface $queue,
    ) {}

    public function enqueue(int $documentId): void
    {
        $this->repository->markQueued($documentId);

        if ($this->queueDriver === 'sync') {
            $this->queue->push(new DocumentMessage($documentId));
            return;
        }

        $this->repository->addEvent(
            $documentId,
            'queue-adapter-unavailable',
            strtoupper($this->queueDriver) . ' queue mode selected, but the installed Yii queue adapter package is incompatible with the installed Yii queue core. See docs/upstream-queue-notes.md.',
            5,
        );
    }
}
