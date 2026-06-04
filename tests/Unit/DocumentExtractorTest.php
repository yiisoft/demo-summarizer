<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Document\Extraction\ExtractionException;
use App\Document\Extraction\NativeExtractor;
use Codeception\Test\Unit;

use function PHPUnit\Framework\assertSame;

final class DocumentExtractorTest extends Unit
{
    public function testMarkdownPassesThrough(): void
    {
        $extractor = new NativeExtractor();

        assertSame('# Title', $extractor->extract(" # Title \n", 'md', 'test.md'));
    }

    public function testHtmlIsConvertedToReadableText(): void
    {
        $extractor = new NativeExtractor();

        assertSame("## Heading\n\nBody text", $extractor->extract('<h1>Heading</h1><p>Body text</p>', 'html', 'test.html'));
    }

    public function testUnsupportedFormatFails(): void
    {
        $this->expectException(ExtractionException::class);

        (new NativeExtractor())->extract('%PDF-', 'pdf', 'test.pdf');
    }
}
