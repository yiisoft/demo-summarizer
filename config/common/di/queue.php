<?php

declare(strict_types=1);

use App\Document\Processing\AmqpDocumentQueuePurger;
use App\Document\Processing\DocumentQueuePurgerInterface;
use App\Document\Processing\NullDocumentQueuePurger;
use App\Document\Processing\RedisDocumentQueuePurger;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Psr\Log\LoggerInterface;
use Yiisoft\Queue\Amqp\Adapter as AmqpAdapter;
use Yiisoft\Queue\Amqp\QueueProvider as AmqpQueueProvider;
use Yiisoft\Queue\Amqp\Settings\Queue as AmqpQueueSettings;
use Yiisoft\Queue\AsyncQueueProducer;
use Yiisoft\Queue\Cli\LoopInterface;
use Yiisoft\Queue\DefaultQueue;
use Yiisoft\Queue\Message\Serializer\MessageSerializerInterface;
use Yiisoft\Queue\Middleware\Push\PushMiddlewareConfig;
use Yiisoft\Queue\Provider\PredefinedQueueProvider;
use Yiisoft\Queue\Provider\QueueConsumerProviderInterface;
use Yiisoft\Queue\Provider\QueueProducerProviderInterface;
use Yiisoft\Queue\QueueConsumer;
use Yiisoft\Queue\QueueProducerInterface;
use Yiisoft\Queue\Redis\Adapter as RedisAdapter;
use Yiisoft\Queue\Redis\QueueProvider as RedisQueueProvider;
use Yiisoft\Queue\SyncQueueProducer;
use Yiisoft\Queue\Worker\WorkerInterface;

/** @var array $params */

return [
    PredefinedQueueProvider::class => static function (
        WorkerInterface $worker,
        LoopInterface $loop,
        LoggerInterface $logger,
        PushMiddlewareConfig $middlewareConfig,
        MessageSerializerInterface $serializer,
    ) use ($params): PredefinedQueueProvider {
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

        $producer = $adapter === null
            ? new SyncQueueProducer($logger, $middlewareConfig, $worker, $queueName)
            : new AsyncQueueProducer($logger, $middlewareConfig, $adapter, $queueName);
        $roles = [
            'producer' => $producer,
            'consumer' => new QueueConsumer($worker, $loop, $logger, $adapter, $queueName),
        ];

        return new PredefinedQueueProvider([
            DefaultQueue::NAME => $roles,
            $queueName => $roles,
        ]);
    },
    QueueProducerProviderInterface::class => PredefinedQueueProvider::class,
    QueueConsumerProviderInterface::class => PredefinedQueueProvider::class,
    QueueProducerInterface::class => static fn (QueueProducerProviderInterface $provider): QueueProducerInterface =>
        $provider->getProducer(DefaultQueue::NAME),
    AmqpDocumentQueuePurger::class => [
        '__construct()' => [
            'queueName' => $params['documentDemo']['queueName'],
            'amqpHost' => $params['documentDemo']['amqpHost'],
            'amqpPort' => $params['documentDemo']['amqpPort'],
            'amqpUser' => $params['documentDemo']['amqpUser'],
            'amqpPassword' => $params['documentDemo']['amqpPassword'],
            'amqpVhost' => $params['documentDemo']['amqpVhost'],
        ],
    ],
    RedisDocumentQueuePurger::class => [
        '__construct()' => [
            'queueName' => $params['documentDemo']['queueName'],
            'redisHost' => $params['documentDemo']['redisHost'],
            'redisPort' => $params['documentDemo']['redisPort'],
            'redisTimeout' => $params['documentDemo']['redisTimeout'],
        ],
    ],
    DocumentQueuePurgerInterface::class => static function (
        AmqpDocumentQueuePurger $amqpPurger,
        NullDocumentQueuePurger $nullPurger,
        RedisDocumentQueuePurger $redisPurger,
    ) use ($params): DocumentQueuePurgerInterface {
        return match ($params['documentDemo']['queueDriver']) {
            'sync' => $nullPurger,
            'amqp' => $amqpPurger,
            'redis' => $redisPurger,
            default => throw new \RuntimeException('QUEUE_DRIVER must be sync, amqp, or redis.'),
        };
    },
];
