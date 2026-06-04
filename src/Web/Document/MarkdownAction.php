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
 * Streams extracted markdown for a processed document.
 */
final readonly class MarkdownAction
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
     * Streams extracted markdown or a 404 response when it is unavailable.
     */
    public function __invoke(): ResponseInterface
    {
        $document = $this->repository->get((int) $this->currentRoute->getArgument('id'));
        if ($document->markdownKey === null) {
            return new Response(404, body: $this->streamFactory->createStream('Markdown has not been extracted yet.'));
        }

        return new Response(
            200,
            [
                'Content-Type' => 'text/markdown; charset=UTF-8',
                'Content-Disposition' => 'inline; filename="document-' . $document->id . '.md"',
            ],
            $this->streamFactory->createStream($this->storage->read($document->markdownKey)),
        );
    }
}
