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
    /**
     * @param DocumentRepository $repository Document persistence gateway.
     * @param DocumentStorageInterface $storage Document blob storage.
     * @param DocumentQueuePurgerInterface $queuePurger Queue backend purger.
     */
    public function __construct(
        private DocumentRepository $repository,
        private DocumentStorageInterface $storage,
        private DocumentQueuePurgerInterface $queuePurger,
    ) {}

    /**
     * Clears pending queue jobs, stored files, and document records.
     */
    public function clear(): void
    {
        $this->queuePurger->purge();
        $this->storage->clear();
        $this->repository->deleteAll();
    }
}
