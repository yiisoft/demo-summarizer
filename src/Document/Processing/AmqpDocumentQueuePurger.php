<?php

declare(strict_types=1);

namespace App\Document\Processing;

use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * Clears pending document jobs from a RabbitMQ queue.
 */
final readonly class AmqpDocumentQueuePurger implements DocumentQueuePurgerInterface
{
    /**
     * @param string $queueName Queue name to purge.
     * @param string $amqpHost AMQP broker host.
     * @param int $amqpPort AMQP broker port.
     * @param string $amqpUser AMQP username.
     * @param string $amqpPassword AMQP password.
     * @param string $amqpVhost AMQP virtual host.
     */
    public function __construct(
        private string $queueName,
        private string $amqpHost,
        private int $amqpPort,
        private string $amqpUser,
        private string $amqpPassword,
        private string $amqpVhost,
    ) {}

    /**
     * Purges pending jobs from RabbitMQ.
     */
    public function purge(): void
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
}
