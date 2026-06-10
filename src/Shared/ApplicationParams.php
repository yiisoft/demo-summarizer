<?php

declare(strict_types=1);

namespace App\Shared;

/**
 * Carries application metadata that is shared with layouts and view templates.
 */
final readonly class ApplicationParams
{
    /**
     * @param non-empty-string $name User-facing application name.
     * @param non-empty-string $charset Response and document character set.
     * @param non-empty-string $locale Default application locale.
     */
    public function __construct(
        public string $name = 'Yii3 Document Summarizer Demo',
        public string $charset = 'UTF-8',
        public string $locale = 'en',
    ) {}
}
