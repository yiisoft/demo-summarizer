<?php

declare(strict_types=1);

namespace App\Document\Infrastructure;

/**
 * Stores and retrieves original documents and extracted markdown blobs.
 */
interface DocumentStorageInterface
{
    public function put(string $key, string $contents): void;

    public function read(string $key): string;

    public function delete(string $key): void;
}
