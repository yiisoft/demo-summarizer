<?php

declare(strict_types=1);

namespace App\Document\Domain;

enum DocumentStatus: string
{
    case UPLOADED = 'uploaded';
    case QUEUED = 'queued';
    case EXTRACTING = 'extracting';
    case SUMMARIZING = 'summarizing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public const ACTIVE = [
        self::UPLOADED,
        self::QUEUED,
        self::EXTRACTING,
        self::SUMMARIZING,
    ];
}
