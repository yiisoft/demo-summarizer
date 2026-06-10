<?php

declare(strict_types=1);

namespace App\Document\Extraction;

/**
 * Extracts readable Markdown text from an uploaded document payload.
 */
interface ExtractorInterface
{
    /**
     * Extracts readable Markdown from document bytes.
     *
     * @param string $contents Original document bytes.
     * @param non-empty-string $extension Lowercase document extension.
     * @param non-empty-string $originalName Original client filename.
     */
    public function extract(string $contents, string $extension, string $originalName): string;
}
