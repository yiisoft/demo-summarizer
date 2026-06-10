<?php

declare(strict_types=1);

namespace App\Document\Infrastructure;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;

/**
 * Implements document blob storage through a Flysystem filesystem.
 */
final readonly class FlysystemDocumentStorage implements DocumentStorageInterface
{
    /**
     * @param FilesystemOperator $filesystem Flysystem filesystem.
     */
    public function __construct(
        private FilesystemOperator $filesystem,
    ) {}

    /**
     * Writes a document blob.
     *
     * @param string $key Storage object key.
     * @param string $contents Blob contents.
     */
    public function put(string $key, string $contents): void
    {
        $this->filesystem->write($key, $contents);
    }

    /**
     * Reads a document blob as a stream resource.
     *
     * @param string $key Storage object key.
     *
     * @return resource
     */
    public function readStream(string $key)
    {
        return $this->filesystem->readStream($key);
    }

    /**
     * Deletes a document blob if it exists.
     *
     * @param string $key Storage object key.
     */
    public function delete(string $key): void
    {
        try {
            $this->filesystem->delete($key);
        } catch (UnableToDeleteFile) {
        }
    }

    /**
     * Clears all document blobs managed by the demo.
     */
    public function clear(): void
    {
        try {
            $this->filesystem->deleteDirectory('documents');
        } catch (UnableToDeleteDirectory) {
        }
    }
}
