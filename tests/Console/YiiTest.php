<?php

declare(strict_types=1);

namespace App\Tests\Console;

use Symfony\Component\Process\Process;
use Testo\Assert;
use Testo\Test;

#[Test]
final class YiiTest
{
    public function base(): void
    {
        Assert::string($this->runApp([]))->contains('Yii Console');
    }

    public function runDefaultQueue(): void
    {
        Assert::string($this->runApp(['queue:run', 'yii-queue']))
            ->contains('Processing queue yii-queue... Messages processed: 0.');
    }

    public function runDocumentQueue(): void
    {
        Assert::string($this->runApp(['queue:run', 'document-demo']))
            ->contains('Processing queue document-demo... Messages processed: 0.');
    }

    private function runApp(array $arguments): string
    {
        $root = dirname(__DIR__, 2);
        $process = new Process([
            PHP_BINARY, '-d', 'auto_prepend_file=' . $root . '/tests/Support/collect-coverage.php',
            $root . '/yii', ...$arguments,
        ], $root, ['TEST_PROCESS_COVERAGE_DIR' => getenv('TEST_PROCESS_COVERAGE_DIR') ?: false], timeout: 30);
        $process->run();
        Assert::same($process->getExitCode(), 0, $process->getErrorOutput() . $process->getOutput());
        return $process->getOutput();
    }
}
