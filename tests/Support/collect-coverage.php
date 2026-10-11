<?php

declare(strict_types=1);

// Loaded only by test child processes; production has no coverage bootstrap.
$coverageDirectory = getenv('TEST_PROCESS_COVERAGE_DIR');
if ($coverageDirectory === false || $coverageDirectory === '') {
    return;
}
if (getenv('APP_ENV') !== 'test' || !extension_loaded('xdebug') || !in_array('coverage', xdebug_info('mode'), true)) {
    throw new RuntimeException('Child-process coverage requires the test environment and Xdebug coverage mode.');
}

$root = dirname(__DIR__, 2);
xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_INCLUDE, [$root . '/src/', $root . '/public/index.php', $root . '/yii']);
xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
register_shutdown_function(static function () use ($coverageDirectory): void {
    $data = xdebug_get_code_coverage();
    xdebug_stop_code_coverage();
    $file = $coverageDirectory . '/' . bin2hex(random_bytes(12)) . '.json';
    if (file_put_contents($file, json_encode($data, JSON_THROW_ON_ERROR)) === false) {
        throw new RuntimeException('Unable to write child-process coverage.');
    }
});
