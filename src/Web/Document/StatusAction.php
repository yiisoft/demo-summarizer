<?php

declare(strict_types=1);

namespace App\Web\Document;

use App\Document\Infrastructure\DocumentRepository;
use HttpSoft\Message\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Yiisoft\Router\CurrentRoute;

use function json_encode;

/**
 * Returns the current document processing status as JSON.
 */
final readonly class StatusAction
{
    public function __construct(
        private CurrentRoute $currentRoute,
        private DocumentRepository $repository,
        private StreamFactoryInterface $streamFactory,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $document = $this->repository->get((int) $this->currentRoute->getArgument('id'));

        return new Response(
            200,
            ['Content-Type' => 'application/json'],
            $this->streamFactory->createStream(
                json_encode([
                    'id' => $document->id,
                    'status' => $document->status,
                    'progress' => $document->progress,
                    'summary' => $document->summary,
                    'error' => $document->error,
                    'updatedAt' => $document->updatedAt,
                ], JSON_THROW_ON_ERROR),
            ),
        );
    }
}
