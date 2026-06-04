<?php

declare(strict_types=1);

namespace App\Document\Summarization;

/**
 * Selects the configured summarizer adapter.
 */
final readonly class ConfiguredSummarizer implements SummarizerInterface
{
    /**
     * @param string $llmAdapter Configured summarizer adapter name.
     * @param MockSummarizer $mockSummarizer Deterministic local summarizer.
     * @param OllamaSummarizer $ollamaSummarizer Host Ollama summarizer.
     */
    public function __construct(
        private string $llmAdapter,
        private MockSummarizer $mockSummarizer,
        private OllamaSummarizer $ollamaSummarizer,
    ) {}

    /**
     * Summarizes markdown through the configured adapter.
     *
     * @param string $markdown Extracted document markdown.
     * @param string $documentName Original document filename.
     */
    public function summarize(string $markdown, string $documentName): string
    {
        return $this->llmAdapter === 'ollama'
            ? $this->ollamaSummarizer->summarize($markdown, $documentName)
            : $this->mockSummarizer->summarize($markdown, $documentName);
    }
}
