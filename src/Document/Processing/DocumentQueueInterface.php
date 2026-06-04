<?php

declare(strict_types=1);

namespace App\Document\Processing;

/**
 * Enqueues a document for asynchronous or synchronous processing.
 */
interface DocumentQueueInterface
{
    public function enqueue(int $documentId): void;
}
