<?php

declare(strict_types=1);

namespace App\Document\Summarization;

final readonly class ConfiguredSummarizer implements SummarizerInterface
{
    public function __construct(
        private string $llmAdapter,
        private MockSummarizer $mockSummarizer,
        private OllamaSummarizer $ollamaSummarizer,
    ) {}

    public function summarize(string $markdown, string $documentName): string
    {
        return $this->llmAdapter === 'ollama'
            ? $this->ollamaSummarizer->summarize($markdown, $documentName)
            : $this->mockSummarizer->summarize($markdown, $documentName);
    }
}
