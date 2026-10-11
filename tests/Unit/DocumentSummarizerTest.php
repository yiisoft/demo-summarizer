<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Document\Summarization\MockSummarizer;
use App\Document\Summarization\OpenAiCompatibleSummarizer;
use Testo\Assert;
use Testo\Expect;
use Testo\Lifecycle\BeforeTest;
use Testo\Lifecycle\AfterTest;
use Testo\Test;
use RuntimeException;

use function json_decode;
use function stream_context_get_options;
use function stream_wrapper_register;
use function stream_wrapper_unregister;

#[Test]
final class DocumentSummarizerTest
{
    /**
     * Registers the fake OpenAI-compatible transport.
     */
    #[BeforeTest]
    public function setUp(): void
    {
        OpenAiCompatibleTestStream::reset();

        if (!stream_wrapper_register('openai-compatible-test', OpenAiCompatibleTestStream::class)) {
            throw new RuntimeException('Unable to register OpenAI-compatible test stream wrapper.');
        }
    }

    /**
     * Unregisters the fake OpenAI-compatible transport.
     */
    #[AfterTest]
    public function tearDown(): void
    {
        stream_wrapper_unregister('openai-compatible-test');
    }

    /**
     * Verifies the mock summarizer uses stable local output.
     */
    public function testMockSummarizerIsDeterministic(): void
    {
        $summary = (new MockSummarizer())->summarize("# Heading\n\nImportant body.", 'notes.md');

        Assert::string($summary)->contains('Mock summary for notes.md:');
        Assert::string($summary)->contains('Important body.');
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

        Assert::same($summary, 'Five concise bullets.');
        Assert::same(OpenAiCompatibleTestStream::$requests[0]['path'], 'openai-compatible-test://localhost/v1/chat/completions');
        Assert::same(OpenAiCompatibleTestStream::$requests[0]['method'], 'POST');
        Assert::same(OpenAiCompatibleTestStream::$requests[0]['header'], "Content-Type: application/json\nAuthorization: Bearer test-token\n");

        /** @var array{model: string, stream: bool, temperature: float, top_p: float, repeat_penalty: float, frequency_penalty: float, presence_penalty: float, max_tokens: int, messages: list<array{role: string, content: string}>} $payload */
        $payload = json_decode(OpenAiCompatibleTestStream::$requests[0]['content'], true);
        Assert::same($payload['model'], 'gemma-3-1b-it-Q4_K_M');
        Assert::same($payload['stream'], false);
        Assert::same($payload['temperature'], 0.01);
        Assert::same($payload['top_p'], 0.7);
        Assert::same($payload['repeat_penalty'], 1.25);
        Assert::same($payload['frequency_penalty'], 0.8);
        Assert::same($payload['presence_penalty'], 0.3);
        Assert::same($payload['max_tokens'], 512);
        Assert::same($payload['messages'][0]['role'], 'user');
        Assert::string($payload['messages'][0]['content'])->contains('Summarize the source text.');
        Assert::string($payload['messages'][0]['content'])->contains('Do not quote the source.');
        Assert::string($payload['messages'][0]['content'])->contains('Output without an introduction.');
        Assert::string($payload['messages'][0]['content'])->contains('Document: notes.md');
        Assert::string($payload['messages'][0]['content'])->contains('Important markdown.');
        Assert::string($payload['messages'][0]['content'])->contains('Summary:');
    }

    /**
     * Verifies unexpected OpenAI-compatible responses fail clearly.
     */
    public function testOpenAiCompatibleSummarizerRejectsUnexpectedResponse(): void
    {
        OpenAiCompatibleTestStream::$response = '{"unexpected":"shape"}';

        Expect::exception(RuntimeException::class)->withMessageContaining('OpenAI-compatible API returned an unexpected response.');

        (new OpenAiCompatibleSummarizer('openai-compatible-test://localhost/v1', 'gemma-3-1b-it-Q4_K_M'))
            ->summarize('Important markdown.', 'notes.md');
    }

    /**
     * Verifies provider error responses are reported with their message.
     */
    public function testOpenAiCompatibleSummarizerReportsProviderError(): void
    {
        OpenAiCompatibleTestStream::$response = '{"error":{"message":"request exceeds the available context size"}}';

        Expect::exception(RuntimeException::class)->withMessageContaining(
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

        Expect::exception(RuntimeException::class)->withMessageContaining(
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
