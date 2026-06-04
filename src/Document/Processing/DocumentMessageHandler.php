<?php

declare(strict_types=1);

namespace App\Document\Processing;

use Yiisoft\Queue\Message\MessageHandlerInterface;
use Yiisoft\Queue\Message\Envelope;
use Yiisoft\Queue\Message\MessageInterface;

/**
 * Handles queued document processing messages.
 */
final readonly class DocumentMessageHandler implements MessageHandlerInterface
{
    public function __construct(
        private DocumentProcessor $processor,
    ) {}

    public function handle(MessageInterface $message): void
    {
        if ($message instanceof Envelope) {
            $message = $message->getMessage();
        }

        if ($message instanceof DocumentMessage) {
            $this->processor->process($message->documentId);
        }
    }
}
