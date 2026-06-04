<?php

declare(strict_types=1);

namespace App\Document\Processing;

/**
 * Carries validation errors for a document upload batch.
 */
final readonly class UploadValidationResult
{
    /**
     * @param list<string> $errors Validation error messages.
     */
    public function __construct(
        public array $errors,
    ) {}

    /**
     * Returns whether the upload batch passed validation.
     */
    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
