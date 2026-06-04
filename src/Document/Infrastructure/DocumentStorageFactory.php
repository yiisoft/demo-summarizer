<?php

declare(strict_types=1);

namespace App\Document\Infrastructure;

use App\Document\DocumentDemoConfig;
use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use RuntimeException;

final readonly class DocumentStorageFactory
{
    public function __construct(
        private DocumentDemoConfig $config,
    ) {}

    public function create(): DocumentStorageInterface
    {
        if ($this->config->storageDriver === 's3') {
            if ($this->config->s3Bucket === '') {
                throw new RuntimeException('S3_BUCKET must be configured when DOCUMENT_STORAGE_DRIVER=s3.');
            }

            $client = new S3Client([
                'version' => 'latest',
                'endpoint' => $this->config->s3Endpoint ?: null,
                'region' => $this->config->s3Region,
                'use_path_style_endpoint' => $this->config->s3PathStyle,
                'credentials' => [
                    'key' => $this->config->s3AccessKey,
                    'secret' => $this->config->s3SecretKey,
                ],
            ]);

            return new FlysystemDocumentStorage(new Filesystem(new AwsS3V3Adapter($client, $this->config->s3Bucket)));
        }

        return new FlysystemDocumentStorage(new Filesystem(new LocalFilesystemAdapter($this->config->localStorageRoot)));
    }
}
