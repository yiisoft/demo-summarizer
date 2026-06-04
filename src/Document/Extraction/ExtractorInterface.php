<?php

declare(strict_types=1);

namespace App\Document\Extraction;

/**
 * Extracts readable markdown text from an uploaded document payload.
 */
interface ExtractorInterface
{
    /**
     * Extracts readable markdown from document bytes.
     *
     * @param string $contents Original document bytes.
     * @param string $extension Lowercase document extension.
     * @param string $originalName Original client filename.
     */
    public function extract(string $contents, string $extension, string $originalName): string;
}
