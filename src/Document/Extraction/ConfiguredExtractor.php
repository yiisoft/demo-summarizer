<?php

declare(strict_types=1);

namespace App\Document\Extraction;

final readonly class ConfiguredExtractor implements ExtractorInterface
{
    public function __construct(
        private string $extractorAdapter,
        private NativeExtractor $nativeExtractor,
        private KreuzbergExtractor $kreuzbergExtractor,
    ) {}

    public function extract(string $contents, string $extension, string $originalName): string
    {
        $markdown = $this->extractorAdapter === 'kreuzberg'
            ? $this->kreuzbergExtractor->extract($contents, $extension, $originalName)
            : $this->nativeExtractor->extract($contents, $extension, $originalName);

        $markdown = trim($markdown);
        if ($markdown === '') {
            throw new ExtractionException('No readable text was extracted from the document.');
        }

        return $markdown;
    }
}
