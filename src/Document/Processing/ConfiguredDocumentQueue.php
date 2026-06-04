<?php

declare(strict_types=1);

namespace App\Document\Processing;

use App\Document\DocumentDemoConfig;
use App\Document\Infrastructure\DocumentRepository;

final readonly class ConfiguredDocumentQueue implements DocumentQueueInterface
{
    public function __construct(
        private DocumentDemoConfig $config,
        private DocumentRepository $repository,
        private DocumentProcessor $processor,
    ) {}

    public function enqueue(int $documentId): void
    {
        $this->repository->markQueued($documentId);

        if ($this->config->queueDriver === 'sync') {
            $this->processor->process($documentId);
            return;
        }

        $this->repository->addEvent(
            $documentId,
            'queue-driver',
            strtoupper($this->config->queueDriver) . ' queue mode selected; run the worker command to process queued documents.',
            5,
        );
    }
}
