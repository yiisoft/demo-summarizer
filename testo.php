<?php

declare(strict_types=1);

use App\Tests\Support\ProcessCoverageReport;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\SuiteConfig;
use Testo\Codecov\CodecovPlugin;
use Testo\Codecov\Config\CoverageMode;
use Testo\Codecov\Report\CloverReport;

require __DIR__ . '/tests/bootstrap.php';

$plugins = [];
if (getenv('TEST_COVERAGE') === '1') {
    $directory = __DIR__ . '/runtime/test-coverage/processes-' . bin2hex(random_bytes(8));
    if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create process coverage directory.');
    }
    putenv('TEST_PROCESS_COVERAGE_DIR=' . $directory);
    $plugins[] = new CodecovPlugin(
        collect: CoverageMode::Always,
        reports: [new ProcessCoverageReport(
            new CloverReport(__DIR__ . '/runtime/test-coverage/clover.xml'),
            $directory,
        )],
    );
}

return new ApplicationConfig(
    src: ['src', 'public/index.php', 'yii'],
    suites: [
        new SuiteConfig(name: 'Unit', location: ['tests/Unit']),
        new SuiteConfig(name: 'Functional', location: ['tests/Functional']),
        new SuiteConfig(name: 'Console', location: ['tests/Console']),
        new SuiteConfig(name: 'Web', location: ['tests/Web']),
    ],
    plugins: $plugins,
);
