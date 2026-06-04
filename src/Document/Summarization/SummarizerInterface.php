<?php

declare(strict_types=1);

namespace App\Document\Summarization;

/**
 * Summarizes extracted document markdown.
 */
interface SummarizerInterface
{
    public function summarize(string $markdown, string $documentName): string;
}
