<?php

declare(strict_types=1);

namespace App\Document\Extraction;

use function html_entity_decode;
use function preg_replace;
use function strip_tags;
use function trim;

/**
 * Extracts text from formats that can be handled without an external runtime.
 */
final class NativeExtractor implements ExtractorInterface
{
    /**
     * Extracts text from natively supported document formats.
     *
     * @param string $contents Original document bytes.
     * @param string $extension Lowercase document extension.
     * @param string $originalName Original client filename.
     */
    public function extract(string $contents, string $extension, string $originalName): string
    {
        return match ($extension) {
            'md', 'txt' => trim($contents),
            'html' => $this->htmlToMarkdownLike($contents),
            default => throw new ExtractionException(
                "The .$extension format requires the Kreuzberg extractor runtime.",
            ),
        };
    }

    /**
     * Converts simple HTML into readable markdown-like plain text.
     *
     * @param string $contents HTML document contents.
     */
    private function htmlToMarkdownLike(string $contents): string
    {
        $contents = preg_replace('~<(h[1-6])[^>]*>(.*?)</\1>~is', "\n\n## $2\n\n", $contents) ?? $contents;
        $contents = preg_replace('~<br\s*/?>~i', "\n", $contents) ?? $contents;
        $contents = preg_replace('~</p\s*>~i', "\n\n", $contents) ?? $contents;
        $text = html_entity_decode(strip_tags($contents), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("~[ \t]+~", ' ', $text) ?? $text;
        $text = preg_replace("~\n{3,}~", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
