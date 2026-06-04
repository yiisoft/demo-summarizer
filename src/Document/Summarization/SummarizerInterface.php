<?php

declare(strict_types=1);

namespace App\Document\Summarization;

/**
 * Summarizes extracted document markdown.
 */
interface SummarizerInterface
{
    /**
     * Summarizes extracted document markdown.
     *
     * @param string $markdown Extracted document markdown.
     * @param string $documentName Original document filename.
     */
    public function summarize(string $markdown, string $documentName): string;
}
