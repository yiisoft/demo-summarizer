<?php

declare(strict_types=1);

namespace App\Document\Extraction;

/**
 * Extracts readable markdown text from an uploaded document payload.
 */
interface ExtractorInterface
{
    public function extract(string $contents, string $extension, string $originalName): string;
}
