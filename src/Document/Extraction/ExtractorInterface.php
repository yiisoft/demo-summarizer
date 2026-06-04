<?php

declare(strict_types=1);

namespace App\Document\Extraction;

interface ExtractorInterface
{
    public function extract(string $contents, string $extension, string $originalName): string;
}
