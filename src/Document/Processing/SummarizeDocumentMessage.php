<?php

declare(strict_types=1);

namespace App\Document\Processing;

use Yiisoft\Queue\Message\Message;

/**
 * Queue message that identifies the document to summarize.
 */
final class SummarizeDocumentMessage extends Message
{
    /**
     * @param int $documentId Document identifier to process.
     */
    public function __construct(
        public readonly int $documentId,
    ) {}

    /**
     * @param string $type Serialized message type.
     * @param mixed $data Serialized document identifier.
     */
    public static function fromData(string $type, mixed $data): self
    {
        return new self((int) $data);
    }

    /**
     * Returns the queue message type.
     */
    public function getType(): string
    {
        return self::class;
    }

    /**
     * Returns the document identifier serialized into the queue payload.
     */
    public function getData(): int
    {
        return $this->documentId;
    }
}
