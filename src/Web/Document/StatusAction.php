<?php

declare(strict_types=1);

namespace App\Web\Document;

use App\Document\Infrastructure\DocumentRepository;
use HttpSoft\Message\Response;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Router\CurrentRoute;

use function json_encode;

final readonly class StatusAction
{
    public function __construct(
        private CurrentRoute $currentRoute,
        private DocumentRepository $repository,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $document = $this->repository->get((int) $this->currentRoute->getArgument('id'));

        return new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode([
                'id' => $document->id,
                'status' => $document->status,
                'progress' => $document->progress,
                'summary' => $document->summary,
                'error' => $document->error,
                'updatedAt' => $document->updatedAt,
            ], JSON_THROW_ON_ERROR),
        );
    }
}
