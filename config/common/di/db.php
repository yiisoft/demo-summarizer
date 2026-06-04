<?php

declare(strict_types=1);

use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;

/** @var array $params */

return [
    ConnectionInterface::class => static function (SchemaCache $schemaCache) use ($params): ConnectionInterface {
        $dsn = $params['documentDemo']['databaseDsn'];
        if (str_starts_with($dsn, 'sqlite:')) {
            $path = substr($dsn, 7);
            if ($path !== ':memory:') {
                $directory = dirname($path);
                if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                    throw new \RuntimeException("Unable to create database directory \"$directory\".");
                }
            }
        }

        return new Connection(new Driver($dsn), $schemaCache);
    },
];
