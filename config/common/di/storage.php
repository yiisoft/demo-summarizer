<?php

declare(strict_types=1);

use App\Document\Infrastructure\DocumentStorageFactory;
use App\Document\Infrastructure\DocumentStorageInterface;

/** @var array $params */

return [
    DocumentStorageFactory::class => [
        '__construct()' => [
            'storageDriver' => $params['documentDemo']['storageDriver'],
            'localStorageRoot' => $params['documentDemo']['localStorageRoot'],
            's3Endpoint' => $params['documentDemo']['s3Endpoint'],
            's3Region' => $params['documentDemo']['s3Region'],
            's3Bucket' => $params['documentDemo']['s3Bucket'],
            's3AccessKey' => $params['documentDemo']['s3AccessKey'],
            's3SecretKey' => $params['documentDemo']['s3SecretKey'],
            's3PathStyle' => $params['documentDemo']['s3PathStyle'],
        ],
    ],
    DocumentStorageInterface::class => static fn (DocumentStorageFactory $factory): DocumentStorageInterface => $factory->create(),
];
