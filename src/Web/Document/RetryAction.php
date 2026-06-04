<?php

declare(strict_types=1);

namespace App\Web\Document;

use App\Document\Infrastructure\DocumentRepository;
use App\Document\Processing\DocumentQueueInterface;
use HttpSoft\Message\Response;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Router\CurrentRoute;

final readonly class RetryAction
{
    public function __construct(
        private CurrentRoute $currentRoute,
        private DocumentRepository $repository,
        private DocumentQueueInterface $queue,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $id = (int) $this->currentRoute->getArgument('id');
        $this->repository->prepareRetry($id);
        $this->queue->enqueue($id);

        return new Response(303, ['Location' => '/documents/' . $id]);
    }
}
