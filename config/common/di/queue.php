<?php

declare(strict_types=1);

use App\Document\Processing\ConfiguredDocumentQueue;
use App\Document\Processing\DocumentQueueInterface;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Psr\Log\LoggerInterface;
use Yiisoft\Queue\AMQP\Adapter as AmqpAdapter;
use Yiisoft\Queue\AMQP\QueueProvider as AmqpQueueProvider;
use Yiisoft\Queue\AMQP\Settings\Queue as AmqpQueueSettings;
use Yiisoft\Queue\Cli\LoopInterface;
use Yiisoft\Queue\Message\MessageSerializerInterface;
use Yiisoft\Queue\Middleware\Push\PushMiddlewareConfig;
use Yiisoft\Queue\Provider\PredefinedQueueProvider;
use Yiisoft\Queue\Provider\QueueProviderInterface;
use Yiisoft\Queue\Queue;
use Yiisoft\Queue\QueueInterface;
use Yiisoft\Queue\Redis\Adapter as RedisAdapter;
use Yiisoft\Queue\Redis\QueueProvider as RedisQueueProvider;
use Yiisoft\Queue\Worker\WorkerInterface;

/** @var array $params */

return [
    QueueInterface::class => static function (
        WorkerInterface $worker,
        LoopInterface $loop,
        LoggerInterface $logger,
        PushMiddlewareConfig $middlewareConfig,
        MessageSerializerInterface $serializer,
    ) use ($params): QueueInterface {
        $driver = $params['documentDemo']['queueDriver'];
        $queueName = $params['documentDemo']['queueName'];
        $createRedis = static function () use ($params): \Redis {
            $redis = new \Redis();
            $redis->connect(
                $params['documentDemo']['redisHost'],
                $params['documentDemo']['redisPort'],
                $params['documentDemo']['redisTimeout'],
            );

            return $redis;
        };

        $adapter = match ($driver) {
            'sync' => null,
            'amqp' => new AmqpAdapter(
                new AmqpQueueProvider(
                    new AMQPStreamConnection(
                        $params['documentDemo']['amqpHost'],
                        $params['documentDemo']['amqpPort'],
                        $params['documentDemo']['amqpUser'],
                        $params['documentDemo']['amqpPassword'],
                        $params['documentDemo']['amqpVhost'],
                    ),
                    new AmqpQueueSettings($queueName),
                ),
                $serializer,
                $loop,
            ),
            'redis' => new RedisAdapter(
                new RedisQueueProvider(
                    $createRedis(),
                    $queueName,
                ),
                $serializer,
                $loop,
            ),
            default => throw new \RuntimeException('QUEUE_DRIVER must be sync, amqp, or redis.'),
        };

        return new Queue($worker, $loop, $logger, $middlewareConfig, $adapter, $queueName);
    },
    QueueProviderInterface::class => static fn (QueueInterface $queue): QueueProviderInterface => new PredefinedQueueProvider([
        QueueProviderInterface::DEFAULT_QUEUE => $queue,
    ]),
    DocumentQueueInterface::class => ConfiguredDocumentQueue::class,
];
