<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Document\Summarization\MockSummarizer;
use App\Document\Summarization\OpenAiCompatibleSummarizer;
use Codeception\Test\Unit;
use RuntimeException;

use function json_decode;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;
use function stream_context_get_options;
use function stream_wrapper_register;
use function stream_wrapper_unregister;

final class DocumentSummarizerTest extends Unit
{
    /**
     * Registers the fake OpenAI-compatible transport.
     */
    protected function _before(): void
    {
        OpenAiCompatibleTestStream::reset();

        if (!stream_wrapper_register('openai-compatible-test', OpenAiCompatibleTestStream::class)) {
            throw new RuntimeException('Unable to register OpenAI-compatible test stream wrapper.');
        }
    }

    /**
     * Unregisters the fake OpenAI-compatible transport.
     */
    protected function _after(): void
    {
        stream_wrapper_unregister('openai-compatible-test');
    }

    /**
     * Verifies the mock summarizer uses stable local output.
     */
    public function testMockSummarizerIsDeterministic(): void
    {
        $summary = (new MockSummarizer())->summarize("# Heading\n\nImportant body.", 'notes.md');

        assertStringContainsString('Mock summary for notes.md:', $summary);
        assertStringContainsString('Important body.', $summary);
    }

    /**
     * Verifies OpenAI-compatible chat completions request handling.
     */
    public function testOpenAiCompatibleSummarizerPostsChatRequestAndReturnsResponse(): void
    {
        OpenAiCompatibleTestStream::$response = '{"choices":[{"message":{"content":"Five concise bullets."}}]}';

        $summary = (new OpenAiCompatibleSummarizer(
            'openai-compatible-test://localhost/v1',
            'gemma-3-1b-it-Q4_K_M',
            'test-token',
        ))->summarize('Important markdown.', 'notes.md');

        assertSame('Five concise bullets.', $summary);
        assertSame(
            'openai-compatible-test://localhost/v1/chat/completions',
            OpenAiCompatibleTestStream::$requests[0]['path'],
        );
        assertSame('POST', OpenAiCompatibleTestStream::$requests[0]['method']);
        assertSame(
            "Content-Type: application/json\nAuthorization: Bearer test-token\n",
            OpenAiCompatibleTestStream::$requests[0]['header'],
        );

        /** @var array{model: string, stream: bool, temperature: float, top_p: float, repeat_penalty: float, frequency_penalty: float, presence_penalty: float, max_tokens: int, messages: list<array{role: string, content: string}>} $payload */
        $payload = json_decode(OpenAiCompatibleTestStream::$requests[0]['content'], true);
        assertSame('gemma-3-1b-it-Q4_K_M', $payload['model']);
        assertSame(false, $payload['stream']);
        assertSame(0.01, $payload['temperature']);
        assertSame(0.7, $payload['top_p']);
        assertSame(1.25, $payload['repeat_penalty']);
        assertSame(0.8, $payload['frequency_penalty']);
        assertSame(0.3, $payload['presence_penalty']);
        assertSame(80, $payload['max_tokens']);
        assertSame('user', $payload['messages'][0]['role']);
        assertStringContainsString('Summarize the source text.', $payload['messages'][0]['content']);
        assertStringContainsString('Do not quote the source.', $payload['messages'][0]['content']);
        assertStringContainsString('Output without an introduction.', $payload['messages'][0]['content']);
        assertStringContainsString('Document: notes.md', $payload['messages'][0]['content']);
        assertStringContainsString('Important markdown.', $payload['messages'][0]['content']);
        assertStringContainsString('Summary:', $payload['messages'][0]['content']);
    }

    /**
     * Verifies unexpected OpenAI-compatible responses fail clearly.
     */
    public function testOpenAiCompatibleSummarizerRejectsUnexpectedResponse(): void
    {
        OpenAiCompatibleTestStream::$response = '{"unexpected":"shape"}';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenAI-compatible API returned an unexpected response.');

        (new OpenAiCompatibleSummarizer('openai-compatible-test://localhost/v1', 'gemma-3-1b-it-Q4_K_M'))
            ->summarize('Important markdown.', 'notes.md');
    }

    /**
     * Verifies provider error responses are reported with their message.
     */
    public function testOpenAiCompatibleSummarizerReportsProviderError(): void
    {
        OpenAiCompatibleTestStream::$response = '{"error":{"message":"request exceeds the available context size"}}';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'OpenAI-compatible API request failed: request exceeds the available context size',
        );

        (new OpenAiCompatibleSummarizer('openai-compatible-test://localhost/v1', 'gemma-3-1b-it-Q4_K_M'))
            ->summarize('Important markdown.', 'notes.md');
    }

    /**
     * Verifies transport failures include the target endpoint.
     */
    public function testOpenAiCompatibleSummarizerReportsTransportFailure(): void
    {
        OpenAiCompatibleTestStream::$open = false;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'OpenAI-compatible summary request to openai-compatible-test://localhost/v1/chat/completions failed',
        );

        (new OpenAiCompatibleSummarizer('openai-compatible-test://localhost/v1', 'gemma-3-1b-it-Q4_K_M'))
            ->summarize('Important markdown.', 'notes.md');
    }
}

final class OpenAiCompatibleTestStream
{
    /** @var resource|null */
    public $context;

    public static string $response = '{"choices":[{"message":{"content":""}}]}';

    public static bool $open = true;

    /** @var list<array{path: string, method: string|null, header: string|null, content: string}> */
    public static array $requests = [];

    private string $body = '';
    private int $position = 0;

    /**
     * Resets fake transport state before each test.
     */
    public static function reset(): void
    {
        self::$response = '{"choices":[{"message":{"content":""}}]}';
        self::$open = true;
        self::$requests = [];
    }

    /**
     * Opens a fake response stream and captures the HTTP context.
     *
     * @param string $path Requested URL.
     * @param string $mode Stream open mode.
     * @param int $options Stream open options.
     * @param string|null $openedPath Opened path passed by reference.
     */
    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        if (!self::$open) {
            return false;
        }

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

    /**
     * Reads from the fake response stream.
     *
     * @param int $count Maximum bytes to read.
     */
    public function stream_read(int $count): string
    {
        $chunk = substr($this->body, $this->position, $count);
        $this->position += strlen($chunk);

        return $chunk;
    }

    /**
     * Reports whether the fake response stream is exhausted.
     */
    public function stream_eof(): bool
    {
        return $this->position >= strlen($this->body);
    }

    /**
     * Returns fake stream metadata.
     *
     * @return array<string, int>
     */
    public function stream_stat(): array
    {
        return [];
    }
}
