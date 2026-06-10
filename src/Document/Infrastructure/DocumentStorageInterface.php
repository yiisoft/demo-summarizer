<?php

declare(strict_types=1);

namespace App\Document\Infrastructure;

/**
 * Stores and retrieves original documents and extracted markdown blobs.
 */
interface DocumentStorageInterface
{
    /**
     * Writes a document blob.
     *
     * @param string $key Storage object key.
     * @param string $contents Blob contents.
     */
    public function put(string $key, string $contents): void;

    /**
     * Reads a document blob as a stream resource.
     *
     * @param string $key Storage object key.
     *
     * @return resource
     */
    public function readStream(string $key);

    /**
     * Deletes a document blob if it exists.
     *
     * @param string $key Storage object key.
     */
    public function delete(string $key): void;

    /**
     * Clears all document blobs managed by the demo.
     */
    public function clear(): void;
}
