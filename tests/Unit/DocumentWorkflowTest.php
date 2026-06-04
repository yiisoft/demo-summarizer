<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Document\Domain\DocumentStatus;
use App\Document\Extraction\ExtractionException;
use App\Document\Extraction\ExtractorInterface;
use App\Document\Infrastructure\DocumentRepository;
use App\Document\Infrastructure\FlysystemDocumentStorage;
use App\Document\Infrastructure\DocumentStorageFactory;
use App\Document\Infrastructure\DocumentStorageInterface;
use App\Document\Migration\M250604000000CreateDocumentTables;
use App\Document\Processing\ConfiguredDocumentQueue;
use App\Document\Processing\DocumentMessage;
use App\Document\Processing\DocumentMessageHandler;
use App\Document\Processing\DocumentProcessor;
use App\Document\Summarization\SummarizerInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\S3Client;
use Codeception\Test\Unit;
use GuzzleHttp\Psr7\Utils;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\Migrator;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;
use Yiisoft\Queue\Message\IdEnvelope;
use Yiisoft\Queue\Message\MessageInterface;
use Yiisoft\Queue\MessageStatus;
use Yiisoft\Queue\QueueInterface;

use function is_file;
use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertSame;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class DocumentWorkflowTest extends Unit
{
    private ?string $databasePath = null;
    private ?string $storageRoot = null;

    protected function _after(): void
    {
        if ($this->databasePath !== null && is_file($this->databasePath)) {
            unlink($this->databasePath);
        }

        if ($this->storageRoot !== null) {
            $this->removeTree($this->storageRoot);
        }
    }

    public function testSchemaCreatesDocumentTables(): void
    {
        $db = $this->database();

        $this->migrate($db);

        $tables = $db->createCommand("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name")
            ->queryColumn();

        self::assertContains('documents', $tables);
        self::assertContains('processing_events', $tables);
    }

    public function testRepositoryRecordsStatusTransitionsAndEvents(): void
    {
        $repository = $this->repository();
        $document = $repository->create('notes.txt', 'documents/1/original.txt', 'text/plain', 'txt', 12);

        $repository->markQueued($document->id);
        $claimed = $repository->claim($document->id, 60);
        self::assertNotNull($claimed);

        $repository->markSummarizing($document->id, 'documents/1/extracted.md');
        $repository->complete($document->id, 'summary');

        $completed = $repository->get($document->id);
        assertSame(DocumentStatus::COMPLETED, $completed->status);
        assertSame(100, $completed->progress);
        assertSame('summary', $completed->summary);
        assertSame('documents/1/extracted.md', $completed->markdownKey);
        assertCount(5, $repository->events($document->id));
    }

    public function testRepositoryDeletesAllDocumentsAndEvents(): void
    {
        $repository = $this->repository();
        $first = $repository->create('first.txt', 'documents/1/original.txt', 'text/plain', 'txt', 12);
        $second = $repository->create('second.txt', 'documents/2/original.txt', 'text/plain', 'txt', 12);

        $repository->deleteAll();

        self::assertSame([], $repository->all());
        self::assertSame([], $repository->events($first->id));
        self::assertSame([], $repository->events($second->id));
    }

    public function testLocalStorageWritesReadsAndDeletesObjects(): void
    {
        $storage = (new DocumentStorageFactory(
            storageDriver: 'local',
            localStorageRoot: $this->storageRoot(),
            s3Endpoint: 'http://garage:3900',
            s3Region: 'garage',
            s3Bucket: 'documents',
            s3AccessKey: 'GKdemo000000000000000000000000000000',
            s3SecretKey: 'garage-demo-secret-key-000000000000000000000000000000',
            s3PathStyle: true,
        ))->create();

        $storage->put('documents/test/original.txt', 'content');

        assertSame('content', $storage->read('documents/test/original.txt'));

        $storage->delete('documents/test/original.txt');

        assertFalse(is_file($this->storageRoot . '/documents/test/original.txt'));
    }

    public function testLocalStorageClearsDocumentObjects(): void
    {
        $storage = (new DocumentStorageFactory(
            storageDriver: 'local',
            localStorageRoot: $this->storageRoot(),
            s3Endpoint: 'http://garage:3900',
            s3Region: 'garage',
            s3Bucket: 'documents',
            s3AccessKey: 'GKdemo000000000000000000000000000000',
            s3SecretKey: 'garage-demo-secret-key-000000000000000000000000000000',
            s3PathStyle: true,
        ))->create();

        $storage->put('documents/1/original.txt', 'first');
        $storage->put('documents/1/extracted.md', 'markdown');
        $storage->put('documents/2/original.txt', 'second');

        $storage->clear();

        assertFalse(is_file($this->storageRoot . '/documents/1/original.txt'));
        assertFalse(is_file($this->storageRoot . '/documents/1/extracted.md'));
        assertFalse(is_file($this->storageRoot . '/documents/2/original.txt'));
    }

    public function testS3StorageUsesFlysystemS3Adapter(): void
    {
        $mock = new MockHandler();
        $mock->append(new Result([]));
        $mock->append(new Result(['Body' => Utils::streamFor('s3 content')]));
        $mock->append(new Result([]));

        $client = new S3Client([
            'version' => 'latest',
            'region' => 'garage',
            'endpoint' => 'http://garage:3900',
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => 'garage',
                'secret' => 'garage-secret',
            ],
            'handler' => $mock,
        ]);
        $storage = new FlysystemDocumentStorage(
            new Filesystem(new AwsS3V3Adapter($client, 'documents')),
        );

        $storage->put('documents/test/original.txt', 'content');
        assertSame('s3 content', $storage->read('documents/test/original.txt'));
        $storage->delete('documents/test/original.txt');
    }

    public function testProcessorCompletesDocument(): void
    {
        $repository = $this->repository();
        $storage = new ArrayDocumentStorage();
        $document = $repository->create('notes.txt', 'documents/1/original.txt', 'text/plain', 'txt', 12);
        $repository->markQueued($document->id);
        $storage->put($document->storageKey, 'Original text');

        $processor = new DocumentProcessor(
            900,
            $repository,
            $storage,
            new StaticExtractor('Extracted markdown'),
            new StaticSummarizer('Summary text'),
        );

        $processor->process($document->id);

        $completed = $repository->get($document->id);
        assertSame(DocumentStatus::COMPLETED, $completed->status);
        assertSame('Summary text', $completed->summary);
        assertSame('Extracted markdown', $storage->read('documents/' . $document->id . '/extracted.md'));
    }

    public function testMessageHandlerProcessesYiiQueueEnvelope(): void
    {
        $repository = $this->repository();
        $storage = new ArrayDocumentStorage();
        $document = $repository->create('notes.txt', 'documents/1/original.txt', 'text/plain', 'txt', 12);
        $repository->markQueued($document->id);
        $storage->put($document->storageKey, 'Original text');

        $handler = new DocumentMessageHandler(new DocumentProcessor(
            900,
            $repository,
            $storage,
            new StaticExtractor('Extracted markdown'),
            new StaticSummarizer('Summary text'),
        ));

        $handler->handle(new IdEnvelope(new DocumentMessage($document->id), 1));

        assertSame(DocumentStatus::COMPLETED, $repository->get($document->id)->status);
    }

    public function testMessageHandlerIgnoresDocumentClearedBeforeProcessing(): void
    {
        $repository = $this->repository();
        $storage = new ArrayDocumentStorage();
        $document = $repository->create('notes.txt', 'documents/1/original.txt', 'text/plain', 'txt', 12);
        $repository->markQueued($document->id);
        $repository->deleteAll();

        $handler = new DocumentMessageHandler(new DocumentProcessor(
            900,
            $repository,
            $storage,
            new StaticExtractor('Extracted markdown'),
            new StaticSummarizer('Summary text'),
        ));

        $handler->handle(new IdEnvelope(new DocumentMessage($document->id), 1));

        self::assertSame([], $repository->all());
    }

    public function testProcessorFailsOnlyCurrentDocument(): void
    {
        $repository = $this->repository();
        $storage = new ArrayDocumentStorage();
        $failing = $repository->create('broken.pdf', 'documents/1/original.pdf', 'application/pdf', 'pdf', 12);
        $other = $repository->create('other.txt', 'documents/2/original.txt', 'text/plain', 'txt', 10);
        $repository->markQueued($failing->id);
        $repository->markQueued($other->id);
        $storage->put($failing->storageKey, '%PDF-');

        $processor = new DocumentProcessor(
            900,
            $repository,
            $storage,
            new FailingExtractor(),
            new StaticSummarizer('unused'),
        );

        $processor->process($failing->id);

        assertSame(DocumentStatus::FAILED, $repository->get($failing->id)->status);
        assertSame(DocumentStatus::QUEUED, $repository->get($other->id)->status);
    }

    public function testProcessorCleansPreviousMarkdownBeforeRetry(): void
    {
        $repository = $this->repository();
        $storage = new ArrayDocumentStorage();
        $document = $repository->create('notes.txt', 'documents/1/original.txt', 'text/plain', 'txt', 12);
        $repository->markQueued($document->id);
        $storage->put($document->storageKey, 'Original text');
        $storage->put('documents/' . $document->id . '/extracted.md', 'Old markdown');

        $processor = new DocumentProcessor(
            900,
            $repository,
            $storage,
            new StaticExtractor('New markdown'),
            new StaticSummarizer('Summary text'),
        );

        $processor->process($document->id);

        assertSame(['documents/' . $document->id . '/extracted.md'], $storage->deleted);
        assertSame('New markdown', $storage->read('documents/' . $document->id . '/extracted.md'));
    }

    public function testDocumentEventsAreRenderedByTimelineModel(): void
    {
        $repository = $this->repository();
        $document = $repository->create('notes.txt', 'documents/1/original.txt', 'text/plain', 'txt', 12);

        $event = $repository->events($document->id)[0];

        assertSame($document->id, $event->documentId);
        assertSame('uploaded', $event->type);
        assertSame('Document uploaded.', $event->message);
    }

    public function testConfiguredQueuePushesSyncJobsThroughYiiQueue(): void
    {
        $repository = $this->repository();
        $document = $repository->create('notes.txt', 'documents/1/original.txt', 'text/plain', 'txt', 12);
        $queue = new CapturingQueue();

        (new ConfiguredDocumentQueue($repository, $queue))->enqueue($document->id);

        assertSame(DocumentStatus::QUEUED, $repository->get($document->id)->status);
        assertCount(1, $queue->messages);
        self::assertInstanceOf(DocumentMessage::class, $queue->messages[0]);
        assertSame($document->id, $queue->messages[0]->documentId);
    }

    private function repository(): DocumentRepository
    {
        $db = $this->database();
        $this->migrate($db);

        return new DocumentRepository($db);
    }

    private function database(): ConnectionInterface
    {
        $this->databasePath ??= sys_get_temp_dir() . '/' . uniqid('doc-demo-', true) . '.sqlite';

        return new Connection(
            new Driver('sqlite:' . $this->databasePath),
            new SchemaCache(new ArrayCache()),
        );
    }

    private function migrate(ConnectionInterface $db): void
    {
        (new Migrator($db, new NullMigrationInformer()))->up(new M250604000000CreateDocumentTables());
    }

    private function storageRoot(): string
    {
        $this->storageRoot ??= sys_get_temp_dir() . '/' . uniqid('doc-storage-', true);

        return $this->storageRoot;
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . '/' . $item;
            if (is_dir($itemPath)) {
                $this->removeTree($itemPath);
            } elseif (is_file($itemPath)) {
                unlink($itemPath);
            }
        }

        rmdir($path);
    }
}

final class ArrayDocumentStorage implements DocumentStorageInterface
{
    /** @var array<string, string> */
    private array $objects = [];

    /** @var list<string> */
    public array $deleted = [];

    public function put(string $key, string $contents): void
    {
        $this->objects[$key] = $contents;
    }

    public function read(string $key): string
    {
        return $this->objects[$key] ?? '';
    }

    public function delete(string $key): void
    {
        $this->deleted[] = $key;
        unset($this->objects[$key]);
    }

    public function clear(): void
    {
        $this->objects = [];
    }
}

final readonly class StaticExtractor implements ExtractorInterface
{
    public function __construct(
        private string $markdown,
    ) {}

    public function extract(string $contents, string $extension, string $originalName): string
    {
        return $this->markdown;
    }
}

final readonly class FailingExtractor implements ExtractorInterface
{
    public function extract(string $contents, string $extension, string $originalName): string
    {
        throw new ExtractionException('Extraction failed.');
    }
}

final readonly class StaticSummarizer implements SummarizerInterface
{
    public function __construct(
        private string $summary,
    ) {}

    public function summarize(string $markdown, string $documentName): string
    {
        return $this->summary;
    }
}

final class CapturingQueue implements QueueInterface
{
    /** @var list<MessageInterface> */
    public array $messages = [];

    public function push(MessageInterface $message): MessageInterface
    {
        $this->messages[] = $message;
        return $message;
    }

    public function run(int $max = 0): int
    {
        return 0;
    }

    public function listen(): void
    {
    }

    public function status(string|int $id): MessageStatus
    {
        return MessageStatus::NOT_FOUND;
    }

    public function getName(): string
    {
        return 'test';
    }

    public function withMiddlewares(mixed ...$middlewareDefinitions): self
    {
        return $this;
    }

    public function withMiddlewaresAdded(mixed ...$middlewareDefinitions): self
    {
        return $this;
    }
}
