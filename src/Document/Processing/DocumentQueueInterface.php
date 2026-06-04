<?php

declare(strict_types=1);

namespace App\Document\Processing;

interface DocumentQueueInterface
{
    public function enqueue(int $documentId): void;
}
