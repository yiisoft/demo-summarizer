<?php

declare(strict_types=1);

namespace App\Document\Extraction;

use function bin2hex;
use function fclose;
use function sys_get_temp_dir;
use function file_put_contents;
use function is_executable;
use function proc_close;
use function proc_open;
use function random_bytes;
use function sprintf;
use function stream_get_contents;
use function trim;
use function unlink;

final readonly class KreuzbergExtractor implements ExtractorInterface
{
    public function __construct(
        private NativeExtractor $fallback = new NativeExtractor(),
        private string $binaryPath = '/usr/local/bin/kreuzberg',
    ) {}

    public function extract(string $contents, string $extension, string $originalName): string
    {
        if (!is_executable($this->binaryPath)) {
            return $this->fallback->extract($contents, $extension, $originalName);
        }

        $path = sprintf(
            '%s/doc-extract-%s.%s',
            sys_get_temp_dir(),
            bin2hex(random_bytes(8)),
            $extension,
        );

        try {
            file_put_contents($path, $contents);

            $process = proc_open(
                [
                    $this->binaryPath,
                    '--log-level',
                    'error',
                    'extract',
                    $path,
                    '--content-format',
                    'markdown',
                    '--format',
                    'text',
                ],
                [
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ],
                $pipes,
            );

            if ($process === false) {
                throw new ExtractionException('Unable to start the Kreuzberg extractor.');
            }

            $markdown = stream_get_contents($pipes[1]) ?: '';
            $error = stream_get_contents($pipes[2]) ?: '';

            fclose($pipes[1]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);
            if ($exitCode !== 0) {
                throw new ExtractionException(
                    trim($error) ?: "Kreuzberg extraction failed for $originalName.",
                );
            }

            return trim($markdown);
        } finally {
            @unlink($path);
        }
    }
}
