<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Document\Extraction\ExtractionException;
use App\Document\Extraction\KreuzbergExtractor;
use App\Document\Extraction\NativeExtractor;
use Codeception\Test\Unit;

use function chmod;
use function file_put_contents;
use function PHPUnit\Framework\assertSame;
use function sys_get_temp_dir;
use function unlink;

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

    public function testKreuzbergCliOutputIsUsed(): void
    {
        $binary = sys_get_temp_dir() . '/fake-kreuzberg-success';
        file_put_contents($binary, "#!/bin/sh\nprintf ' ## Extracted markdown \\n'\n");
        chmod($binary, 0755);

        try {
            $extractor = new KreuzbergExtractor(binaryPath: $binary);

            assertSame('## Extracted markdown', $extractor->extract('binary content', 'docx', 'test.docx'));
        } finally {
            @unlink($binary);
        }
    }

    public function testKreuzbergCliErrorFailsDocument(): void
    {
        $binary = sys_get_temp_dir() . '/fake-kreuzberg-failure';
        file_put_contents($binary, "#!/bin/sh\nprintf 'bad document' >&2\nexit 23\n");
        chmod($binary, 0755);

        try {
            $this->expectException(ExtractionException::class);
            $this->expectExceptionMessage('bad document');

            (new KreuzbergExtractor(binaryPath: $binary))->extract('broken content', 'pdf', 'broken.pdf');
        } finally {
            @unlink($binary);
        }
    }

    public function testKreuzbergFallsBackWhenCliIsMissing(): void
    {
        $extractor = new KreuzbergExtractor(binaryPath: sys_get_temp_dir() . '/missing-kreuzberg');

        assertSame('Plain text', $extractor->extract(' Plain text ', 'txt', 'test.txt'));
    }
}
