<?php

declare(strict_types=1);

namespace App\Document\Processing;

use App\Document\Domain\Document;
use App\Document\Infrastructure\DocumentRepository;
use App\Document\Infrastructure\DocumentStorageInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Yiisoft\Queue\QueueInterface;
use Yiisoft\Validator\Rule\Count as CountRule;
use Yiisoft\Validator\Rule\Each;
use Yiisoft\Validator\Rule\File;
use Yiisoft\Validator\ValidatorInterface;

use function intdiv;
use function pathinfo;
use function strtolower;
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
     * @param list<string> $allowedMimeTypes Allowed MIME types.
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
        private array $allowedMimeTypes,
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
        $errors = $this->validate($files);
        if ($errors !== []) {
            return ['documents' => [], 'errors' => $errors];
        }

        $documents = [];
        foreach ($files as $file) {
            $name = $file->getClientFilename() ?: 'document';
            $extension = $this->extension($name);
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
     *
     * @return list<string> Validation error messages.
     */
    public function validate(array $files): array
    {
        $files = $this->submittedFiles($files);
        if ($files === []) {
            return ['Choose at least one document.'];
        }

        $result = $this->validator->validate($files, [
            new CountRule(
                max: $this->maxFiles,
                greaterThanMaxMessage: 'Upload no more than {max} documents at once.',
            ),
            new Each([
                new File(
                    extensions: $this->allowedExtensions,
                    mimeTypes: $this->allowedMimeTypes,
                    maxSize: $this->maxFileBytes,
                    message: '{file} is not a valid document.',
                    uploadFailedMessage: '{file} is larger than ' . $this->megabytes($this->maxFileBytes) . ' MB.',
                    wrongExtensionMessage: '{file} is not an allowed document.',
                    wrongMimeTypeMessage: '{file} is not a recognized document type.',
                    tooBigMessage: '{file} is larger than ' . $this->megabytes($this->maxFileBytes) . ' MB.',
                    unableToDetermineSizeMessage: 'The size of {file} cannot be determined.',
                ),
            ], skipOnError: true),
        ]);
        $errors = $result->getErrorMessages();
        $validationErrorsByPath = $result->getErrorMessagesIndexedByPath(escape: null);
        if (isset($validationErrorsByPath[''])) {
            return $errors;
        }

        $batchBytes = 0;
        foreach ($files as $index => $file) {
            if (isset($validationErrorsByPath[(string) $index])) {
                continue;
            }

            $batchBytes += $file->getSize() ?? 0;
        }

        if ($batchBytes > $this->maxBatchBytes) {
            $errors[] = 'The upload batch is larger than ' . $this->megabytes($this->maxBatchBytes) . ' MB.';
        }

        return $errors;
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
            static fn(UploadedFileInterface $file): bool => $file->getError() !== UPLOAD_ERR_NO_FILE,
        ));
    }

    /**
     * Returns the lowercase extension for a file accepted by validation.
     *
     * @return non-empty-string
     */
    private function extension(string $name): string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($extension === '') {
            throw new RuntimeException('Uploaded document extension must be non-empty after validation.');
        }

        return $extension;
    }

    /**
     * Converts bytes to rounded-up megabytes for validation messages.
     *
     * TODO: Remove when https://github.com/yiisoft/validator/issues/802 is implemented.
     *
     * @param int $bytes Byte count.
     */
    private function megabytes(int $bytes): int
    {
        return intdiv($bytes + 1024 * 1024 - 1, 1024 * 1024);
    }
}
