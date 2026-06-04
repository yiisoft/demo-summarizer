<?php

declare(strict_types=1);

namespace App\Document\Processing;

/**
 * Clears pending document processing jobs from the configured queue backend.
 */
interface DocumentQueuePurgerInterface
{
    /**
     * Purges pending jobs from the configured queue backend.
     */
    public function purge(): void;
}
