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
    public function __construct(
        private DocumentUploadService $uploadService,
        private DocumentRepository $repository,
        private string $queueDriver,
        private WebViewRenderer $viewRenderer,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $result = $this->uploadService->upload($this->files($request->getUploadedFiles()['documents'] ?? []));
        if ($result['errors'] !== []) {
            return $this->viewRenderer->render(
                '@src/Web/HomePage/template',
                [
                    'documents' => $this->repository->all(),
                    'queueDriver' => $this->queueDriver,
                    'errors' => $result['errors'],
                ],
            );
        }

        return new Response(303, ['Location' => '/']);
    }

    /**
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
