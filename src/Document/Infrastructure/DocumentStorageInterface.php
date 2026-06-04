<?php

declare(strict_types=1);

namespace App\Document\Infrastructure;

interface DocumentStorageInterface
{
    public function put(string $key, string $contents): void;

    public function read(string $key): string;

    public function delete(string $key): void;
}
