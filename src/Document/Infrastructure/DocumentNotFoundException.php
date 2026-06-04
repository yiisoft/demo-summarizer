<?php

declare(strict_types=1);

namespace App\Document\Infrastructure;

use RuntimeException;

/**
 * Signals that a requested document record does not exist.
 */
final class DocumentNotFoundException extends RuntimeException
{
    /**
     * @param int $id Missing document identifier.
     */
    public function __construct(int $id)
    {
        parent::__construct("Document #$id was not found.");
    }
}
