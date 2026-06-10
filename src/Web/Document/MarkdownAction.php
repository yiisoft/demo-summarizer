<?php

declare(strict_types=1);

namespace App\Web\Document;

use App\Document\Infrastructure\DocumentRepository;
use App\Document\Infrastructure\DocumentStorageInterface;
use HttpSoft\Message\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Yiisoft\Router\HydratorAttribute\RouteArgument;

/**
 * Streams extracted markdown for a processed document.
 */
final readonly class MarkdownAction
{
    /**
     * @param DocumentRepository $repository Document persistence gateway.
     * @param DocumentStorageInterface $storage Document blob storage.
     * @param StreamFactoryInterface $streamFactory PSR-7 stream factory.
     */
    public function __construct(
        private DocumentRepository $repository,
        private DocumentStorageInterface $storage,
        private StreamFactoryInterface $streamFactory,
    ) {}

    /**
     * Streams extracted markdown or a 404 response when it is unavailable.
     */
    public function __invoke(#[RouteArgument] int $id): ResponseInterface
    {
        $document = $this->repository->get($id);
        if ($document->markdownKey === null) {
            return new Response(404, body: $this->streamFactory->createStream('Markdown has not been extracted yet.'));
        }

        return new Response(
            200,
            [
                'Content-Type' => 'text/markdown; charset=UTF-8',
                'Content-Disposition' => 'inline; filename="document-' . $document->id . '.md"',
            ],
            $this->streamFactory->createStreamFromResource($this->storage->readStream($document->markdownKey)),
        );
    }
}
