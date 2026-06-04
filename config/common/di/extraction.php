<?php

declare(strict_types=1);

use App\Document\Extraction\ExtractorInterface;
use App\Document\Extraction\KreuzbergExtractor;
use App\Document\Extraction\NativeExtractor;

/** @var array $params */

return [
    ExtractorInterface::class => static function (
        KreuzbergExtractor $kreuzbergExtractor,
        NativeExtractor $nativeExtractor,
    ) use ($params): ExtractorInterface {
        return match ($params['documentDemo']['extractorAdapter']) {
            'kreuzberg' => $kreuzbergExtractor,
            'native' => $nativeExtractor,
            default => throw new RuntimeException('EXTRACTOR_ADAPTER must be kreuzberg or native.'),
        };
    },
];
