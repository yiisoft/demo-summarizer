<?php

declare(strict_types=1);

namespace App\Document\Processing;

use Yiisoft\Queue\Message\MessageHandlerInterface;
use Yiisoft\Queue\Message\Envelope;
use Yiisoft\Queue\Message\MessageInterface;

/**
 * Handles queued document summarization messages.
 */
final readonly class SummarizeDocumentMessageHandler implements MessageHandlerInterface
{
    /**
     * @param DocumentProcessor $processor Document processing workflow.
     */
    public function __construct(
        private DocumentProcessor $processor,
    ) {}

    /**
     * @param MessageInterface $message Yii queue message or envelope.
     */
    public function handle(MessageInterface $message): void
    {
        if ($message instanceof Envelope) {
            $message = $message->getMessage();
        }

        if ($message instanceof SummarizeDocumentMessage) {
            $this->processor->process($message->documentId);
        }
    }
}
