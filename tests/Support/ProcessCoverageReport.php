<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Testo\Codecov\Report\CoverageReport;
use Testo\Codecov\Result\CoverageResult;
use Testo\Core\Report\ReportInfo;

/** Merges test-only HTTP and console process coverage into Testo's report. */
final readonly class ProcessCoverageReport implements CoverageReport
{
    public function __construct(
        private CoverageReport $report,
        private string $directory,
    ) {}

    public function generate(CoverageResult $result): void
    {
        foreach (glob($this->directory . '/*.json') ?: [] as $file) {
            $raw = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
            $result = $result->merge(CoverageResult::fromRawData($raw));
        }
        $this->report->generate($result);
    }

    public function info(): ReportInfo
    {
        return $this->report->info();
    }
}
