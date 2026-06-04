<?php

declare(strict_types=1);

namespace App\Document\Processing;

use App\Document\Domain\Document;
use App\Document\Infrastructure\DocumentRepository;
use App\Document\Infrastructure\DocumentStorageInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Yiisoft\Validator\Rule\In;
use Yiisoft\Validator\Rule\Number;
use Yiisoft\Validator\ValidatorInterface;

use function array_key_exists;
use function count;
use function explode;
use function in_array;
use function pathinfo;
use function strtolower;
use function trim;
use function uniqid;

use const PATHINFO_EXTENSION;
use const UPLOAD_ERR_OK;

final readonly class DocumentUploadService
{
    /**
     * @param list<string> $allowedExtensions
     */
    public function __construct(
        private DocumentRepository $repository,
        private DocumentStorageInterface $storage,
        private DocumentQueueInterface $queue,
        private ValidatorInterface $validator,
        private int $maxFileBytes,
        private int $maxBatchBytes,
        private array $allowedExtensions,
    ) {}

    /**
     * @param list<UploadedFileInterface> $files
     *
     * @return array{documents: list<Document>, errors: list<string>}
     */
    public function upload(array $files): array
    {
        $validation = $this->validate($files);
        if (!$validation->isValid()) {
            return ['documents' => [], 'errors' => $validation->errors];
        }

        $documents = [];
        foreach ($files as $file) {
            $name = $file->getClientFilename() ?: 'document';
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $key = 'documents/' . uniqid('', true) . '/original.' . $extension;
            $contents = (string) $file->getStream();

            $this->storage->put($key, $contents);
            $document = $this->repository->create(
                $name,
                $key,
                $file->getClientMediaType() ?: 'application/octet-stream',
                $extension,
                $file->getSize() ?? strlen($contents),
            );
            $documents[] = $document;
            $this->queue->enqueue($document->id);
        }

        return ['documents' => $documents, 'errors' => []];
    }

    /**
     * @param list<UploadedFileInterface> $files
     */
    public function validate(array $files): UploadValidationResult
    {
        if ($files === []) {
            return new UploadValidationResult(['Choose at least one document.']);
        }

        $errors = [];
        $batchBytes = 0;
        foreach ($files as $index => $file) {
            $name = $file->getClientFilename() ?: 'document #' . ($index + 1);
            if ($file->getError() !== UPLOAD_ERR_OK) {
                $errors[] = "$name could not be uploaded.";
                continue;
            }

            $size = $file->getSize() ?? 0;
            $batchBytes += $size;
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            $result = $this->validator->validate(
                [
                    'extension' => $extension,
                    'size' => $size,
                ],
                [
                    'extension' => [new In($this->allowedExtensions)],
                    'size' => [new Number(max: $this->maxFileBytes)],
                ],
            );

            if (!$result->isValid()) {
                $errors[] = "$name is not an allowed document or is larger than 20 MB.";
                continue;
            }

            if (!$this->signatureLooksValid($file, $extension)) {
                $errors[] = "$name does not match the expected file signature.";
            }
        }

        if ($batchBytes > $this->maxBatchBytes) {
            $errors[] = 'The upload batch is larger than 100 MB.';
        }

        return new UploadValidationResult($errors);
    }

    private function signatureLooksValid(UploadedFileInterface $file, string $extension): bool
    {
        $stream = $file->getStream();
        $position = $stream->tell();
        $stream->rewind();
        $head = $stream->read(512);
        $stream->seek($position);

        $trimmed = trim($head);
        if (in_array($extension, ['md', 'txt'], true)) {
            return $trimmed !== '';
        }

        if ($extension === 'html') {
            return str_contains(strtolower($head), '<html') || str_contains(strtolower($head), '<!doctype html');
        }

        if ($extension === 'pdf') {
            return str_starts_with($head, '%PDF-');
        }

        if ($extension === 'docx') {
            $parts = explode("\n", $head);
            return array_key_exists(0, $parts) && str_starts_with($parts[0], 'PK');
        }

        return false;
    }
}
