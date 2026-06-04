<?php

declare(strict_types=1);

namespace App\Document\Infrastructure;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToDeleteFile;

/**
 * Implements document blob storage through a Flysystem filesystem.
 */
final readonly class FlysystemDocumentStorage implements DocumentStorageInterface
{
    public function __construct(
        private FilesystemOperator $filesystem,
    ) {}

    public function put(string $key, string $contents): void
    {
        $this->filesystem->write($key, $contents);
    }

    public function read(string $key): string
    {
        return $this->filesystem->read($key);
    }

    public function delete(string $key): void
    {
        try {
            $this->filesystem->delete($key);
        } catch (UnableToDeleteFile) {
        }
    }
}
