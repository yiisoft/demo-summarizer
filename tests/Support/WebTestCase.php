<?php

declare(strict_types=1);

namespace App\Tests\Support;

use RuntimeException;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Process\Process;
use Testo\Assert;
use Testo\Lifecycle\AfterClass;
use Testo\Lifecycle\BeforeClass;
use Testo\Lifecycle\BeforeTest;

abstract class WebTestCase
{
    private static ?Process $server = null;
    protected static string $baseUrl;
    protected HttpBrowser $browser;

    #[BeforeClass]
    public static function startServer(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        if ($socket === false) {
            throw new RuntimeException('Unable to reserve a test-server port.');
        }
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        if ($address === false) {
            throw new RuntimeException('Unable to determine test-server address.');
        }
        self::$baseUrl = 'http://' . $address;
        $root = dirname(__DIR__, 2);
        // Unlike `yii serve`, this gives Process direct ownership of the server,
        // so teardown can stop it without leaving shell children listening.
        self::$server = new Process([
            PHP_BINARY, '-S', $address, '-t', $root . '/public', __DIR__ . '/router.php',
        ], $root, [
            'PHP_CLI_SERVER_WORKERS' => false,
            'TEST_PROCESS_COVERAGE_DIR' => getenv('TEST_PROCESS_COVERAGE_DIR') ?: false,
        ], timeout: null);
        self::$server->start();
        $deadline = microtime(true) + 10;
        do {
            $connection = @stream_socket_client('tcp://' . $address, timeout: 0.1);
            if ($connection !== false) {
                fclose($connection);
                return;
            }
            if (!self::$server->isRunning()) {
                break;
            }
            usleep(20_000);
        } while (microtime(true) < $deadline);
        $output = self::$server->getErrorOutput();
        self::stopServer();
        throw new RuntimeException('Test HTTP server did not start: ' . $output);
    }

    #[AfterClass]
    public static function stopServer(): void
    {
        self::$server?->stop(1);
        self::$server = null;
    }

    #[BeforeTest]
    public function createBrowser(): void
    {
        $this->browser = new HttpBrowser(HttpClient::create(['timeout' => 30]));
    }

    protected function open(string $path): void
    {
        $this->browser->request('GET', self::$baseUrl . $path);
    }

    protected function see(string $text): void
    {
        Assert::string($this->pageText())->contains($text);
    }

    protected function dontSee(string $text): void
    {
        Assert::string($this->pageText())->notContains($text);
    }

    private function pageText(): string
    {
        $body = $this->browser->getCrawler()->filter('body');
        return $body->count() > 0 ? $body->text() : $this->browser->getResponse()->getContent();
    }

    protected function seePath(string $path): void
    {
        Assert::same(parse_url($this->browser->getRequest()->getUri(), PHP_URL_PATH), $path);
    }
}
