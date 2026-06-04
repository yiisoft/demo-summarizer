<?php

declare(strict_types=1);

namespace App\Web\Document;

use App\Document\Infrastructure\DocumentRepository;
use App\Document\Infrastructure\DocumentStorageInterface;
use HttpSoft\Message\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Yiisoft\Router\CurrentRoute;

/**
 * Streams an original uploaded document for download.
 */
final readonly class DownloadAction
{
    /**
     * @param CurrentRoute $currentRoute Current route with the document identifier.
     * @param DocumentRepository $repository Document persistence gateway.
     * @param DocumentStorageInterface $storage Document blob storage.
     * @param StreamFactoryInterface $streamFactory PSR-7 stream factory.
     */
    public function __construct(
        private CurrentRoute $currentRoute,
        private DocumentRepository $repository,
        private DocumentStorageInterface $storage,
        private StreamFactoryInterface $streamFactory,
    ) {}

    /**
     * Streams the original uploaded file.
     */
    public function __invoke(): ResponseInterface
    {
        $document = $this->repository->get((int) $this->currentRoute->getArgument('id'));

        return new Response(
            200,
            [
                'Content-Type' => $document->mimeType,
                'Content-Disposition' => 'attachment; filename="' . addslashes($document->originalName) . '"',
            ],
            $this->streamFactory->createStream($this->storage->read($document->storageKey)),
        );
    }
}
