<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Tests\Support\ProcessCoverageReport;
use DOMDocument;
use DOMXPath;
use Testo\Assert;
use Testo\Codecov\Report\CloverReport;
use Testo\Codecov\Result\CoverageResult;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
final class ProcessCoverageReportTest
{
    private string $directory;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/testo-coverage-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    #[AfterTest]
    public function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function childHitsAreMergedWithParentCoverage(): void
    {
        $source = '/app/src/example.php';
        file_put_contents($this->directory . '/request.json', json_encode([
            $source => [10 => 1, 11 => -1],
            '/app/public/index.php' => [20 => 1],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($this->directory . '/console.json', json_encode([
            $source => [11 => 1, 12 => -1],
        ], JSON_THROW_ON_ERROR));
        $report = new ProcessCoverageReport(new CloverReport($this->directory . '/clover.xml'), $this->directory);
        $report->generate(CoverageResult::fromRawData([$source => [10 => -1, 11 => -1, 13 => 1]]));
        $document = new DOMDocument();
        Assert::true($document->load($this->directory . '/clover.xml'));
        $xml = new DOMXPath($document);
        Assert::count($xml->query('//file[@name="/app/src/example.php"]/line[@count="1"]'), 3);
        Assert::count($xml->query('//file[@name="/app/src/example.php"]/line[@num="12"][@count="0"]'), 1);
        Assert::count($xml->query('//file[@name="/app/public/index.php"]/line[@num="20"][@count="1"]'), 1);
    }

    public function noChildFilesPreservesParentCoverage(): void
    {
        $report = new ProcessCoverageReport(new CloverReport($this->directory . '/clover.xml'), $this->directory);
        $report->generate(CoverageResult::fromRawData(['/app/src/example.php' => [10 => 1]]));
        $document = new DOMDocument();
        Assert::true($document->load($this->directory . '/clover.xml'));
        $xml = new DOMXPath($document);
        Assert::count($xml->query('//file/line[@num="10"][@count="1"]'), 1);
    }
}
