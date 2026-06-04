<?php

declare(strict_types=1);

use App\Document\Migration\M250604000000CreateDocumentTables;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\Migrator;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;

App\Environment::prepare();

$dsn = getenv('DATABASE_DSN');

if (App\Environment::isTest() && is_string($dsn) && str_starts_with($dsn, 'sqlite:')) {
    $path = substr($dsn, 7);

    if ($path !== ':memory:' && is_file($path)) {
        unlink($path);
    }

    $connection = new Connection(
        new Driver($dsn),
        new SchemaCache(new ArrayCache()),
    );

    (new Migrator($connection, new NullMigrationInformer()))
        ->up(new M250604000000CreateDocumentTables());
}
