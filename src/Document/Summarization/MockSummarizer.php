<?php

declare(strict_types=1);

namespace App\Document\Summarization;

use function mb_substr;
use function preg_replace;
use function trim;

/**
 * Produces deterministic summaries for tests and local mock mode.
 */
final class MockSummarizer implements SummarizerInterface
{
    public function summarize(string $markdown, string $documentName): string
    {
        $slice = trim(mb_substr(preg_replace('~\s+~u', ' ', $markdown) ?? $markdown, 0, 700));

        return $slice === ''
            ? "No summary could be generated for $documentName."
            : "Mock summary for $documentName: $slice";
    }
}
