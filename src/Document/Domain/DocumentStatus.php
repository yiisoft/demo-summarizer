<?php

declare(strict_types=1);

namespace App\Document\Domain;

final class DocumentStatus
{
    public const UPLOADED = 'uploaded';
    public const QUEUED = 'queued';
    public const EXTRACTING = 'extracting';
    public const SUMMARIZING = 'summarizing';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';

    public const ACTIVE = [
        self::UPLOADED,
        self::QUEUED,
        self::EXTRACTING,
        self::SUMMARIZING,
    ];
}
