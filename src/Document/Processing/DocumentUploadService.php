<?php

declare(strict_types=1);

namespace App\Document\Processing;

use App\Document\Domain\Document;
use App\Document\Infrastructure\DocumentRepository;
use App\Document\Infrastructure\DocumentStorageInterface;
use Psr\Http\Message\UploadedFileInterface;
use Yiisoft\Queue\QueueInterface;
use Yiisoft\Validator\Rule\File;
use Yiisoft\Validator\ValidatorInterface;

use function array_key_exists;
use function count;
use function explode;
use function in_array;
use function intdiv;
use function pathinfo;
use function strtolower;
use function trim;
use function uniqid;

use const PATHINFO_EXTENSION;
use const UPLOAD_ERR_NO_FILE;

/**
 * Validates uploaded files, stores originals, creates records, and enqueues processing.
 */
final readonly class DocumentUploadService
{
    /**
     * @param DocumentRepository $repository Document persistence gateway.
     * @param DocumentStorageInterface $storage Document blob storage.
     * @param QueueInterface $queue Yii queue used for processing messages.
     * @param ValidatorInterface $validator Yii validator.
     * @param int $maxFiles Maximum number of files in one upload batch.
     * @param int $maxFileBytes Maximum file size in bytes.
     * @param int $maxBatchBytes Maximum batch size in bytes.
     * @param list<string> $allowedExtensions Allowed lowercase file extensions.
     */
    public function __construct(
        private DocumentRepository $repository,
        private DocumentStorageInterface $storage,
        private QueueInterface $queue,
        private ValidatorInterface $validator,
        private int $maxFiles,
        private int $maxFileBytes,
        private int $maxBatchBytes,
        private array $allowedExtensions,
    ) {}

    /**
     * Validates, stores, creates, and queues uploaded documents.
     *
     * @param list<UploadedFileInterface> $files Uploaded document files.
     *
     * @return array{documents: list<Document>, errors: list<string>}
     */
    public function upload(array $files): array
    {
        $files = $this->submittedFiles($files);
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
            $this->repository->markQueued($document->id);
            $this->queue->push(new SummarizeDocumentMessage($document->id));
        }

        return ['documents' => $documents, 'errors' => []];
    }

    /**
     * Validates an upload batch.
     *
     * @param list<UploadedFileInterface> $files Uploaded document files.
     */
    public function validate(array $files): UploadValidationResult
    {
        $files = $this->submittedFiles($files);
        if ($files === []) {
            return new UploadValidationResult(['Choose at least one document.']);
        }

        $errors = [];
        if (count($files) > $this->maxFiles) {
            $errors[] = 'Upload no more than ' . $this->maxFiles . ' documents at once.';
        }

        $batchBytes = 0;
        foreach ($files as $index => $file) {
            $name = $file->getClientFilename() ?: 'document #' . ($index + 1);
            $result = $this->validator->validate($file, [
                new File(
                    extensions: $this->allowedExtensions,
                    maxSize: $this->maxFileBytes,
                    message: "$name is not a valid document.",
                    uploadFailedMessage: "$name is larger than " . $this->megabytes($this->maxFileBytes) . ' MB.',
                    wrongExtensionMessage: "$name is not an allowed document.",
                    tooBigMessage: "$name is larger than " . $this->megabytes($this->maxFileBytes) . ' MB.',
                    unableToDetermineSizeMessage: "The size of $name cannot be determined.",
                ),
            ]);

            if (!$result->isValid()) {
                foreach ($result->getErrorMessages() as $error) {
                    $errors[] = $error;
                }
                continue;
            }

            $size = $file->getSize() ?? 0;
            $batchBytes += $size;
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (!$this->signatureLooksValid($file, $extension)) {
                $errors[] = "$name does not match the expected file signature.";
            }
        }

        if ($batchBytes > $this->maxBatchBytes) {
            $errors[] = 'The upload batch is larger than ' . $this->megabytes($this->maxBatchBytes) . ' MB.';
        }

        return new UploadValidationResult($errors);
    }

    /**
     * Removes empty file-upload slots from a batch.
     *
     * @param list<UploadedFileInterface> $files Uploaded document files.
     *
     * @return list<UploadedFileInterface>
     */
    private function submittedFiles(array $files): array
    {
        return array_values(array_filter(
            $files,
            static fn (UploadedFileInterface $file): bool => $file->getError() !== UPLOAD_ERR_NO_FILE,
        ));
    }

    /**
     * Converts bytes to rounded-up megabytes for validation messages.
     *
     * @param int $bytes Byte count.
     */
    private function megabytes(int $bytes): int
    {
        return intdiv($bytes + 1024 * 1024 - 1, 1024 * 1024);
    }

    /**
     * Checks the first bytes of a file against the expected extension signature.
     *
     * @param UploadedFileInterface $file Uploaded file.
     * @param string $extension Lowercase file extension.
     */
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
