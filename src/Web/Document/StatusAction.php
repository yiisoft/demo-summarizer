<?php

declare(strict_types=1);

namespace App\Web\Document;

use App\Document\Infrastructure\DocumentRepository;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\DataResponse\ResponseFactory\JsonResponseFactory;
use Yiisoft\Router\HydratorAttribute\RouteArgument;

/**
 * Returns the current document processing status as JSON.
 */
final readonly class StatusAction
{
    /**
     * @param DocumentRepository $repository Document persistence gateway.
     * @param JsonResponseFactory $responseFactory JSON response factory.
     */
    public function __construct(
        private DocumentRepository $repository,
        private JsonResponseFactory $responseFactory,
    ) {}

    /**
     * Returns the selected document status as JSON.
     */
    public function __invoke(#[RouteArgument] int $id): ResponseInterface
    {
        $document = $this->repository->get($id);

        return $this->responseFactory->createResponse(new DocumentStatusResponse(
            id: $document->id,
            status: $document->status->value,
            progress: $document->progress,
            summary: $document->summary,
            error: $document->error,
            updatedAt: $document->updatedAt,
        ));
    }
}
