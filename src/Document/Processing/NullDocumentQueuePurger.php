<?php

declare(strict_types=1);

namespace App\Document\Processing;

/**
 * Queue purger for synchronous processing where no pending backend queue exists.
 */
final class NullDocumentQueuePurger implements DocumentQueuePurgerInterface
{
    /**
     * Leaves synchronous processing untouched because it has no queue backlog.
     */
    public function purge(): void {}
}
