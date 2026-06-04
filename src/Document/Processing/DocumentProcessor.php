<?php

declare(strict_types=1);

namespace App\Document\Processing;

use App\Document\Extraction\ExtractorInterface;
use App\Document\Infrastructure\DocumentRepository;
use App\Document\Infrastructure\DocumentStorageInterface;
use App\Document\Summarization\SummarizerInterface;
use Throwable;

/**
 * Runs the document processing workflow from extraction through summary completion.
 */
final readonly class DocumentProcessor
{
    public function __construct(
        private int $leaseSeconds,
        private DocumentRepository $repository,
        private DocumentStorageInterface $storage,
        private ExtractorInterface $extractor,
        private SummarizerInterface $summarizer,
    ) {}

    public function process(int $documentId): void
    {
        $document = $this->repository->claim($documentId, $this->leaseSeconds);
        if ($document === null) {
            return;
        }

        try {
            $original = $this->storage->read($document->storageKey);
            $markdown = $this->extractor->extract($original, $document->extension, $document->originalName);
            $markdownKey = 'documents/' . $document->id . '/extracted.md';
            if ($document->markdownKey !== null && $document->markdownKey !== $markdownKey) {
                $this->storage->delete($document->markdownKey);
            }
            $this->storage->delete($markdownKey);
            $this->storage->put($markdownKey, $markdown);
            $this->repository->markSummarizing($document->id, $markdownKey);

            $summary = $this->summarizer->summarize($markdown, $document->originalName);
            $this->repository->complete($document->id, $summary);
        } catch (Throwable $throwable) {
            $this->repository->fail(
                $document->id,
                $throwable->getMessage(),
                $throwable::class . ': ' . $throwable->getMessage() . "\n" . $throwable->getTraceAsString(),
            );
        }
    }
}
