<?php

declare(strict_types=1);

namespace App\Document\Processing;

use Yiisoft\Queue\Message\MessageInterface;

final readonly class DocumentMessage implements MessageInterface
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public int $documentId,
        private array $metadata = [],
    ) {}

    public static function fromData(string $type, mixed $data): self
    {
        return new self((int) $data);
    }

    public function getType(): string
    {
        return self::class;
    }

    public function getData(): int
    {
        return $this->documentId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function withMetadata(array $metadata): static
    {
        return new self($this->documentId, $metadata);
    }
}
