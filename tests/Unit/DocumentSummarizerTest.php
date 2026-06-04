<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Document\Summarization\MockSummarizer;
use Codeception\Test\Unit;

use function PHPUnit\Framework\assertStringContainsString;

final class DocumentSummarizerTest extends Unit
{
    public function testMockSummarizerIsDeterministic(): void
    {
        $summary = (new MockSummarizer())->summarize("# Heading\n\nImportant body.", 'notes.md');

        assertStringContainsString('Mock summary for notes.md:', $summary);
        assertStringContainsString('Important body.', $summary);
    }
}
