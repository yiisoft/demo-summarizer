<?php

declare(strict_types=1);

namespace App\Document\Processing;

use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * Clears jobs from the queue backend configured for document processing.
 */
final readonly class ConfiguredDocumentQueuePurger implements DocumentQueuePurgerInterface
{
    /**
     * @param string $queueDriver Active queue driver name.
     * @param string $queueName Queue name to purge.
     * @param string $amqpHost AMQP broker host.
     * @param int $amqpPort AMQP broker port.
     * @param string $amqpUser AMQP username.
     * @param string $amqpPassword AMQP password.
     * @param string $amqpVhost AMQP virtual host.
     * @param string $redisHost Redis-compatible host.
     * @param int $redisPort Redis-compatible port.
     * @param int $redisTimeout Redis connection timeout in seconds.
     */
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

    /**
     * Purges pending jobs from the active non-sync queue backend.
     */
    public function purge(): void
    {
        match ($this->queueDriver) {
            'sync' => null,
            'amqp' => $this->purgeAmqp(),
            'redis' => $this->purgeRedis(),
            default => throw new \RuntimeException('QUEUE_DRIVER must be sync, amqp, or redis.'),
        };
    }

    /**
     * Purges pending jobs from RabbitMQ.
     */
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

    /**
     * Purges pending jobs from Redis-compatible queue keys.
     */
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
