<?php

declare(strict_types=1);

namespace App\Document\Extraction;

use function class_exists;
use function function_exists;
use function is_object;
use function is_string;
use function method_exists;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use function file_put_contents;

final readonly class KreuzbergExtractor implements ExtractorInterface
{
    public function __construct(
        private NativeExtractor $fallback = new NativeExtractor(),
    ) {}

    public function extract(string $contents, string $extension, string $originalName): string
    {
        if (!function_exists('\\Kreuzberg\\extract_file')) {
            return $this->fallback->extract($contents, $extension, $originalName);
        }

        $path = tempnam(sys_get_temp_dir(), 'doc-extract-');
        if ($path === false) {
            throw new ExtractionException('Unable to allocate a temporary extraction file.');
        }

        try {
            file_put_contents($path, $contents);
            $extract = '\\Kreuzberg\\extract_file';
            $result = $extract($path);

            if (is_string($result)) {
                return $result;
            }

            if (is_object($result) && method_exists($result, 'getMarkdown')) {
                return (string) $result->getMarkdown();
            }

            if (is_object($result) && method_exists($result, 'getText')) {
                return (string) $result->getText();
            }

            if (class_exists('Stringable') && $result instanceof \Stringable) {
                return (string) $result;
            }
        } finally {
            @unlink($path);
        }

        throw new ExtractionException('Kreuzberg returned an unsupported extraction result.');
    }
}
