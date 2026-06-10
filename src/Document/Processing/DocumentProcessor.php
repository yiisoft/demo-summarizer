<?php

declare(strict_types=1);

namespace App\Document\Processing;

use App\Document\Extraction\ExtractionException;
use App\Document\Extraction\ExtractorInterface;
use App\Document\Infrastructure\DocumentNotFoundException;
use App\Document\Infrastructure\DocumentRepository;
use App\Document\Infrastructure\DocumentStorageInterface;
use App\Document\Summarization\SummarizerInterface;
use RuntimeException;
use Throwable;

use function stream_get_contents;
use function trim;

/**
 * Runs the document processing workflow from extraction through summary completion.
 */
final readonly class DocumentProcessor
{
    /**
     * @param int $leaseSeconds Processing lease duration in seconds.
     * @param DocumentRepository $repository Document persistence gateway.
     * @param DocumentStorageInterface $storage Document blob storage.
     * @param ExtractorInterface $extractor Document text extractor.
     * @param SummarizerInterface $summarizer Document summarizer.
     */
    public function __construct(
        private int $leaseSeconds,
        private DocumentRepository $repository,
        private DocumentStorageInterface $storage,
        private ExtractorInterface $extractor,
        private SummarizerInterface $summarizer,
    ) {}

    /**
     * Processes a queued document if it can be claimed.
     *
     * @param int $documentId Document identifier to process.
     */
    public function process(int $documentId): void
    {
        try {
            $document = $this->repository->claim($documentId, $this->leaseSeconds);
        } catch (DocumentNotFoundException) {
            return;
        }

        if ($document === null) {
            return;
        }

        try {
            $original = stream_get_contents($this->storage->readStream($document->storageKey));
            if ($original === false) {
                throw new RuntimeException('Unable to read document content.');
            }

            $markdown = trim($this->extractor->extract($original, $document->extension, $document->originalName));
            if ($markdown === '') {
                throw new ExtractionException('No readable text was extracted from the document.');
            }

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
            );
        }
    }
}
