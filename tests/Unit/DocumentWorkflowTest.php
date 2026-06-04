<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Document\DocumentDemoConfig;
use App\Document\Domain\DocumentStatus;
use App\Document\Extraction\ExtractionException;
use App\Document\Extraction\ExtractorInterface;
use App\Document\Infrastructure\DocumentDatabase;
use App\Document\Infrastructure\DocumentRepository;
use App\Document\Infrastructure\DocumentSchema;
use App\Document\Infrastructure\DocumentStorageFactory;
use App\Document\Infrastructure\DocumentStorageInterface;
use App\Document\Processing\DocumentProcessor;
use App\Document\Summarization\SummarizerInterface;
use Codeception\Test\Unit;
use PDO;

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
        $database = $this->database();

        (new DocumentSchema($database))->migrate();

        $tables = $database->pdo()
            ->query("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name")
            ->fetchAll(PDO::FETCH_COLUMN);

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

    public function testLocalStorageWritesReadsAndDeletesObjects(): void
    {
        $storage = (new DocumentStorageFactory($this->config()))->create();

        $storage->put('documents/test/original.txt', 'content');

        assertSame('content', $storage->read('documents/test/original.txt'));

        $storage->delete('documents/test/original.txt');

        assertFalse(is_file($this->storageRoot . '/documents/test/original.txt'));
    }

    public function testProcessorCompletesDocument(): void
    {
        $repository = $this->repository();
        $storage = new ArrayDocumentStorage();
        $document = $repository->create('notes.txt', 'documents/1/original.txt', 'text/plain', 'txt', 12);
        $repository->markQueued($document->id);
        $storage->put($document->storageKey, 'Original text');

        $processor = new DocumentProcessor(
            $this->config(),
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
            $this->config(),
            $repository,
            $storage,
            new FailingExtractor(),
            new StaticSummarizer('unused'),
        );

        $processor->process($failing->id);

        assertSame(DocumentStatus::FAILED, $repository->get($failing->id)->status);
        assertSame(DocumentStatus::QUEUED, $repository->get($other->id)->status);
    }

    private function repository(): DocumentRepository
    {
        $database = $this->database();

        return new DocumentRepository($database, new DocumentSchema($database));
    }

    private function database(): DocumentDatabase
    {
        $this->databasePath ??= sys_get_temp_dir() . '/' . uniqid('doc-demo-', true) . '.sqlite';

        return new DocumentDatabase('sqlite:' . $this->databasePath);
    }

    private function config(): DocumentDemoConfig
    {
        $this->storageRoot ??= sys_get_temp_dir() . '/' . uniqid('doc-storage-', true);

        return new DocumentDemoConfig(
            queueDriver: 'sync',
            databaseDsn: 'sqlite:' . ($this->databasePath ?? ':memory:'),
            storageDriver: 'local',
            localStorageRoot: $this->storageRoot,
            s3Endpoint: 'http://minio:9000',
            s3Region: 'us-east-1',
            s3Bucket: 'documents',
            s3AccessKey: 'minioadmin',
            s3SecretKey: 'minioadmin',
            s3PathStyle: true,
            leaseSeconds: 900,
            extractorAdapter: 'native',
            llmAdapter: 'mock',
            ollamaBaseUrl: 'http://ollama:11434',
            ollamaModel: 'llama3.2',
            maxFileBytes: 20 * 1024 * 1024,
            maxBatchBytes: 100 * 1024 * 1024,
            allowedExtensions: ['md', 'txt', 'html', 'pdf', 'docx'],
        );
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
        unset($this->objects[$key]);
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
