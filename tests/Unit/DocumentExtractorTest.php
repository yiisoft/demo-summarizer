<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Document\Extraction\ExtractionException;
use App\Document\Extraction\KreuzbergExtractor;
use App\Document\Extraction\NativeExtractor;
use Testo\Assert;
use Testo\Expect;
use Testo\Test;

use function chmod;
use function file_put_contents;
use function sys_get_temp_dir;
use function unlink;

#[Test]
final class DocumentExtractorTest
{
    public function testMarkdownPassesThrough(): void
    {
        $extractor = new NativeExtractor();

        Assert::same($extractor->extract(" # Title \n", 'md', 'test.md'), '# Title');
    }

    public function testHtmlIsConvertedToReadableText(): void
    {
        $extractor = new NativeExtractor();

        Assert::same($extractor->extract('<h1>Heading</h1><p>Body text</p>', 'html', 'test.html'), "## Heading\n\nBody text");
    }

    public function testUnsupportedFormatFails(): void
    {
        Expect::exception(ExtractionException::class);

        (new NativeExtractor())->extract('%PDF-', 'pdf', 'test.pdf');
    }

    public function testKreuzbergCliOutputIsUsed(): void
    {
        $binary = sys_get_temp_dir() . '/fake-kreuzberg-success';
        file_put_contents($binary, "#!/bin/sh\nprintf ' ## Extracted markdown \\n'\n");
        chmod($binary, 0755);

        try {
            $extractor = new KreuzbergExtractor(binaryPath: $binary);

            Assert::same($extractor->extract('binary content', 'docx', 'test.docx'), '## Extracted markdown');
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
            Expect::exception(ExtractionException::class)->withMessageContaining('bad document');

            (new KreuzbergExtractor(binaryPath: $binary))->extract('broken content', 'pdf', 'broken.pdf');
        } finally {
            @unlink($binary);
        }
    }

    public function testKreuzbergFallsBackWhenCliIsMissing(): void
    {
        $extractor = new KreuzbergExtractor(binaryPath: sys_get_temp_dir() . '/missing-kreuzberg');

        Assert::same($extractor->extract(' Plain text ', 'txt', 'test.txt'), 'Plain text');
    }
}
