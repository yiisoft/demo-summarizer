<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Document\Summarization\MockSummarizer;
use App\Document\Summarization\OllamaSummarizer;
use Codeception\Test\Unit;
use RuntimeException;

use function json_decode;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertSame;
use function stream_context_get_options;
use function stream_wrapper_register;
use function stream_wrapper_unregister;

final class DocumentSummarizerTest extends Unit
{
    protected function _before(): void
    {
        OllamaTestStream::reset();

        if (!stream_wrapper_register('ollama-test', OllamaTestStream::class)) {
            throw new RuntimeException('Unable to register Ollama test stream wrapper.');
        }
    }

    protected function _after(): void
    {
        stream_wrapper_unregister('ollama-test');
    }

    public function testMockSummarizerIsDeterministic(): void
    {
        $summary = (new MockSummarizer())->summarize("# Heading\n\nImportant body.", 'notes.md');

        assertStringContainsString('Mock summary for notes.md:', $summary);
        assertStringContainsString('Important body.', $summary);
    }

    public function testOllamaSummarizerPostsGenerateRequestAndReturnsResponse(): void
    {
        OllamaTestStream::$response = '{"response":"Five concise bullets."}';

        $summary = (new OllamaSummarizer('ollama-test://localhost', 'llama3.2'))
            ->summarize('Important markdown.', 'notes.md');

        assertSame('Five concise bullets.', $summary);
        assertSame('ollama-test://localhost/api/generate', OllamaTestStream::$requests[0]['path']);
        assertSame('POST', OllamaTestStream::$requests[0]['method']);
        assertSame("Content-Type: application/json\n", OllamaTestStream::$requests[0]['header']);

        /** @var array{model: string, stream: bool, prompt: string} $payload */
        $payload = json_decode(OllamaTestStream::$requests[0]['content'], true);
        assertSame('llama3.2', $payload['model']);
        assertSame(false, $payload['stream']);
        assertStringContainsString('Document: notes.md', $payload['prompt']);
        assertStringContainsString('Important markdown.', $payload['prompt']);
    }

    public function testOllamaSummarizerRejectsUnexpectedResponse(): void
    {
        OllamaTestStream::$response = '{"unexpected":"shape"}';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ollama returned an unexpected response.');

        (new OllamaSummarizer('ollama-test://localhost', 'llama3.2'))
            ->summarize('Important markdown.', 'notes.md');
    }
}

final class OllamaTestStream
{
    /** @var resource|null */
    public $context;

    public static string $response = '{"response":""}';

    /** @var list<array{path: string, method: string|null, header: string|null, content: string}> */
    public static array $requests = [];

    private string $body = '';
    private int $position = 0;

    public static function reset(): void
    {
        self::$response = '{"response":""}';
        self::$requests = [];
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $context = $this->context === null ? [] : stream_context_get_options($this->context);
        /** @var array{method?: string, header?: string, content?: string} $http */
        $http = $context['http'] ?? [];

        self::$requests[] = [
            'path' => $path,
            'method' => $http['method'] ?? null,
            'header' => $http['header'] ?? null,
            'content' => $http['content'] ?? '',
        ];

        $this->body = self::$response;
        $this->position = 0;

        return true;
    }

    public function stream_read(int $count): string
    {
        $chunk = substr($this->body, $this->position, $count);
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen($this->body);
    }

    /**
     * @return array<string, int>
     */
    public function stream_stat(): array
    {
        return [];
    }
}
