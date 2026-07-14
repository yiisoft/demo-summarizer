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
     * @param bool|int|float|string|array|null $payload Serialized document identifier.
     */
    public static function fromPayload(string $type, bool|int|float|string|array|null $payload): static
    {
        if (!is_int($payload) && !is_string($payload)) {
            throw new \InvalidArgumentException('Document ID payload must be an integer or numeric string.');
        }

        return new self((int) $payload);
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
    public function getPayload(): int
    {
        return $this->documentId;
    }
}
