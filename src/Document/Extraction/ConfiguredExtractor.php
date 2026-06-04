<?php

declare(strict_types=1);

namespace App\Document\Extraction;

/**
 * Selects the configured extraction adapter for uploaded document contents.
 */
final readonly class ConfiguredExtractor implements ExtractorInterface
{
    /**
     * @param string $extractorAdapter Configured extractor adapter name.
     * @param NativeExtractor $nativeExtractor Native fallback extractor.
     * @param KreuzbergExtractor $kreuzbergExtractor Kreuzberg CLI extractor.
     */
    public function __construct(
        private string $extractorAdapter,
        private NativeExtractor $nativeExtractor,
        private KreuzbergExtractor $kreuzbergExtractor,
    ) {}

    /**
     * Extracts non-empty markdown through the configured adapter.
     *
     * @param string $contents Original document bytes.
     * @param string $extension Lowercase document extension.
     * @param string $originalName Original client filename.
     */
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
