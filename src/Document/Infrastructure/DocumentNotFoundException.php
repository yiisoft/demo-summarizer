<?php

declare(strict_types=1);

namespace App\Document\Infrastructure;

use RuntimeException;

final class DocumentNotFoundException extends RuntimeException
{
    public function __construct(int $id)
    {
        parent::__construct("Document #$id was not found.");
    }
}
