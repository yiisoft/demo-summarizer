<?php

declare(strict_types=1);

/**
 * @var Closure(non-empty-string, string): string $env
 */

return [
    'queueDriver' => $env('QUEUE_DRIVER', 'sync'),
    'queueName' => $env('QUEUE_NAME', 'document-demo'),
    'amqpHost' => $env('AMQP_HOST', 'rabbitmq'),
    'amqpPort' => (int) $env('AMQP_PORT', '5672'),
    'amqpUser' => $env('AMQP_USER', 'guest'),
    'amqpPassword' => $env('AMQP_PASSWORD', 'guest'),
    'amqpVhost' => $env('AMQP_VHOST', '/'),
    'redisHost' => $env('REDIS_HOST', 'valkey'),
    'redisPort' => (int) $env('REDIS_PORT', '6379'),
    'redisTimeout' => (int) $env('REDIS_TIMEOUT', '3'),
];
