<?php

declare(strict_types=1);

namespace App\Web\Document;

use App\Document\Infrastructure\DocumentRepository;
use App\Document\Processing\SummarizeDocumentMessage;
use HttpSoft\Message\Response;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Queue\QueueInterface;
use Yiisoft\Router\HydratorAttribute\RouteArgument;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * Resets a failed document and enqueues it for another processing attempt.
 */
final readonly class RetryAction
{
    /**
     * @param DocumentRepository $repository Document persistence gateway.
     * @param QueueInterface $queue Yii queue used for the retry message.
     * @param UrlGeneratorInterface $urlGenerator Yii route URL generator.
     */
    public function __construct(
        private DocumentRepository $repository,
        private QueueInterface $queue,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * Resets the selected document and queues summarization again.
     */
    public function __invoke(#[RouteArgument] int $id): ResponseInterface
    {
        $this->repository->prepareRetry($id);
        $this->repository->markQueued($id);
        $this->queue->push(new SummarizeDocumentMessage($id));

        return new Response(303, ['Location' => $this->urlGenerator->generate('documents/detail', ['id' => $id])]);
    }
}
