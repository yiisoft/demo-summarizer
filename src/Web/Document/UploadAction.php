<?php

declare(strict_types=1);

namespace App\Web\Document;

use App\Document\Infrastructure\DocumentRepository;
use App\Document\Processing\DocumentUploadService;
use HttpSoft\Message\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Handles upload form submissions and redirects or re-renders validation errors.
 */
final readonly class UploadAction
{
    /**
     * @param DocumentUploadService $uploadService Upload workflow service.
     * @param DocumentRepository $repository Document persistence gateway.
     * @param string $queueDriver Active queue driver name.
     * @param int $workers Configured queue worker count.
     * @param string $extractorAdapter Active document extractor adapter.
     * @param string $llmAdapter Active LLM adapter.
     * @param string $llmModel Active LLM model name.
     * @param string $storageDriver Active document storage driver.
     * @param WebViewRenderer $viewRenderer Yii view renderer.
     */
    public function __construct(
        private DocumentUploadService $uploadService,
        private DocumentRepository $repository,
        private string $queueDriver,
        private int $workers,
        private string $extractorAdapter,
        private string $llmAdapter,
        private string $llmModel,
        private string $storageDriver,
        private WebViewRenderer $viewRenderer,
    ) {}

    /**
     * Handles an upload request and redirects or re-renders validation errors.
     *
     * @param ServerRequestInterface $request Upload form request.
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $result = $this->uploadService->upload($this->files($request->getUploadedFiles()['documents'] ?? []));
        if ($result['errors'] !== []) {
            return $this->viewRenderer->render(
                __DIR__ . '/../HomePage/template',
                [
                    'documents' => $this->repository->all(),
                    'queueDriver' => $this->queueDriver,
                    'workers' => $this->queueDriver === 'sync' ? 0 : $this->workers,
                    'extractorAdapter' => $this->extractorAdapter,
                    'llmAdapter' => $this->llmAdapter,
                    'llmModel' => $this->llmModel,
                    'storageDriver' => $this->storageDriver,
                    'errors' => $result['errors'],
                ],
            );
        }

        return new Response(303, ['Location' => '/']);
    }

    /**
     * Normalizes the uploaded files value for the documents input.
     *
     * @param mixed $files
     *
     * @return list<UploadedFileInterface>
     */
    private function files(mixed $files): array
    {
        if ($files instanceof UploadedFileInterface) {
            return [$files];
        }

        if (!is_array($files)) {
            return [];
        }

        return array_values(array_filter(
            $files,
            static fn (mixed $file): bool => $file instanceof UploadedFileInterface,
        ));
    }
}
