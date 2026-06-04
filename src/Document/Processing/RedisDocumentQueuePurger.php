<?php

declare(strict_types=1);

namespace App\Document\Processing;

/**
 * Clears pending document jobs from Redis-compatible Yii Queue keys.
 */
final readonly class RedisDocumentQueuePurger implements DocumentQueuePurgerInterface
{
    /**
     * @param string $queueName Queue name to purge.
     * @param string $redisHost Redis-compatible host.
     * @param int $redisPort Redis-compatible port.
     * @param int $redisTimeout Redis connection timeout in seconds.
     */
    public function __construct(
        private string $queueName,
        private string $redisHost,
        private int $redisPort,
        private int $redisTimeout,
    ) {}

    /**
     * Purges pending jobs from Redis-compatible queue keys.
     */
    public function purge(): void
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
