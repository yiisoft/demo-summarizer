<?php

declare(strict_types=1);

namespace App\Web\Document;

use App\Document\Infrastructure\DocumentRepository;
use App\Document\Processing\SummarizeDocumentMessage;
use HttpSoft\Message\Response;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Queue\QueueInterface;
use Yiisoft\Router\CurrentRoute;

/**
 * Resets a failed document and enqueues it for another processing attempt.
 */
final readonly class RetryAction
{
    /**
     * @param CurrentRoute $currentRoute Current route with the document identifier.
     * @param DocumentRepository $repository Document persistence gateway.
     * @param QueueInterface $queue Yii queue used for the retry message.
     */
    public function __construct(
        private CurrentRoute $currentRoute,
        private DocumentRepository $repository,
        private QueueInterface $queue,
    ) {}

    /**
     * Resets the selected document and queues summarization again.
     */
    public function __invoke(): ResponseInterface
    {
        $id = (int) $this->currentRoute->getArgument('id');
        $this->repository->prepareRetry($id);
        $this->repository->markQueued($id);
        $this->queue->push(new SummarizeDocumentMessage($id));

        return new Response(303, ['Location' => '/documents/' . $id]);
    }
}
