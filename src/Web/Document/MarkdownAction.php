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
    public function __construct(
        private CurrentRoute $currentRoute,
        private DocumentRepository $repository,
        private DocumentStorageInterface $storage,
        private StreamFactoryInterface $streamFactory,
    ) {}

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
