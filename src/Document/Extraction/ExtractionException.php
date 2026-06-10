<?php

declare(strict_types=1);

namespace App\Document\Extraction;

use RuntimeException;

/**
 * Signals that document text extraction failed.
 */
final class ExtractionException extends RuntimeException {}
