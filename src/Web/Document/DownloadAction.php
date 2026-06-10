<?php

declare(strict_types=1);

namespace App\Web\Document;

use App\Document\Infrastructure\DocumentRepository;
use App\Document\Infrastructure\DocumentStorageInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Yiisoft\ResponseDownload\DownloadResponseFactory;
use Yiisoft\Router\HydratorAttribute\RouteArgument;

/**
 * Streams an original uploaded document for download.
 */
final readonly class DownloadAction
{
    /**
     * @param DocumentRepository $repository Document persistence gateway.
     * @param DocumentStorageInterface $storage Document blob storage.
     * @param StreamFactoryInterface $streamFactory PSR-7 stream factory.
     * @param DownloadResponseFactory $downloadResponseFactory Download response factory.
     */
    public function __construct(
        private DocumentRepository $repository,
        private DocumentStorageInterface $storage,
        private StreamFactoryInterface $streamFactory,
        private DownloadResponseFactory $downloadResponseFactory,
    ) {}

    /**
     * Streams the original uploaded file.
     */
    public function __invoke(#[RouteArgument] int $id): ResponseInterface
    {
        $document = $this->repository->get($id);

        return $this->downloadResponseFactory->sendStreamAsFile(
            $this->streamFactory->createStreamFromResource($this->storage->readStream($document->storageKey)),
            attachmentName: $document->originalName,
            mimeType: $document->mimeType,
        );
    }
}
