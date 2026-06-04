<?php

declare(strict_types=1);

namespace App\Document\Processing;

use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * Clears jobs from the queue backend configured for document processing.
 */
final readonly class ConfiguredDocumentQueuePurger implements DocumentQueuePurgerInterface
{
    public function __construct(
        private string $queueDriver,
        private string $queueName,
        private string $amqpHost,
        private int $amqpPort,
        private string $amqpUser,
        private string $amqpPassword,
        private string $amqpVhost,
        private string $redisHost,
        private int $redisPort,
        private int $redisTimeout,
    ) {}

    public function purge(): void
    {
        match ($this->queueDriver) {
            'sync' => null,
            'amqp' => $this->purgeAmqp(),
            'redis' => $this->purgeRedis(),
            default => throw new \RuntimeException('QUEUE_DRIVER must be sync, amqp, or redis.'),
        };
    }

    private function purgeAmqp(): void
    {
        $connection = new AMQPStreamConnection(
            $this->amqpHost,
            $this->amqpPort,
            $this->amqpUser,
            $this->amqpPassword,
            $this->amqpVhost,
        );

        try {
            $channel = $connection->channel();
            $channel->queue_declare($this->queueName, false, true, false, false);
            $channel->queue_purge($this->queueName);
            $channel->close();
        } finally {
            $connection->close();
        }
    }

    private function purgeRedis(): void
    {
        $redis = new \Redis();
        $redis->connect($this->redisHost, $this->redisPort, $this->redisTimeout);

        try {
            $redis->del([
                $this->queueName . '.attempts',
                $this->queueName . '.delayed',
                $this->queueName . '.message_id',
                $this->queueName . '.messages',
                $this->queueName . '.moving_lock',
                $this->queueName . '.reserved',
                $this->queueName . '.waiting',
            ]);
        } finally {
            $redis->close();
        }
    }
}
