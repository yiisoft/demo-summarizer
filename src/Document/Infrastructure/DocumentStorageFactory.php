<?php

declare(strict_types=1);

namespace App\Document\Infrastructure;

use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use RuntimeException;

/**
 * Creates the configured document blob storage adapter.
 */
final readonly class DocumentStorageFactory
{
    /**
     * @param string $storageDriver Storage driver name.
     * @param string $localStorageRoot Root path for local filesystem storage.
     * @param string $s3Endpoint S3-compatible endpoint URL.
     * @param string $s3Region S3 region.
     * @param string $s3Bucket S3 bucket name.
     * @param string $s3AccessKey S3 access key.
     * @param string $s3SecretKey S3 secret key.
     * @param bool $s3PathStyle Whether to use S3 path-style addressing.
     */
    public function __construct(
        private string $storageDriver,
        private string $localStorageRoot,
        private string $s3Endpoint,
        private string $s3Region,
        private string $s3Bucket,
        private string $s3AccessKey,
        private string $s3SecretKey,
        private bool $s3PathStyle,
    ) {}

    /**
     * Creates the configured document storage adapter.
     */
    public function create(): DocumentStorageInterface
    {
        if ($this->storageDriver === 's3') {
            if ($this->s3Bucket === '') {
                throw new RuntimeException('S3_BUCKET must be configured when DOCUMENT_STORAGE_DRIVER=s3.');
            }

            $client = new S3Client([
                'version' => 'latest',
                'endpoint' => $this->s3Endpoint ?: null,
                'region' => $this->s3Region,
                'use_path_style_endpoint' => $this->s3PathStyle,
                'credentials' => [
                    'key' => $this->s3AccessKey,
                    'secret' => $this->s3SecretKey,
                ],
            ]);

            return new FlysystemDocumentStorage(new Filesystem(new AwsS3V3Adapter($client, $this->s3Bucket)));
        }

        return new FlysystemDocumentStorage(new Filesystem(new LocalFilesystemAdapter($this->localStorageRoot)));
    }
}
