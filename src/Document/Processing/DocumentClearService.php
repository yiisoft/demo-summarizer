<?php

declare(strict_types=1);

namespace App\Document\Processing;

use App\Document\Infrastructure\DocumentRepository;
use App\Document\Infrastructure\DocumentStorageInterface;

/**
 * Clears document records, stored document blobs, and pending queue jobs.
 */
final readonly class DocumentClearService
{
    public function __construct(
        private DocumentRepository $repository,
        private DocumentStorageInterface $storage,
        private DocumentQueuePurgerInterface $queuePurger,
    ) {}

    public function clear(): void
    {
        $this->queuePurger->purge();
        $this->storage->clear();
        $this->repository->deleteAll();
    }
}
