<?php

declare(strict_types=1);

namespace App\Web\HomePage;

use App\Document\Infrastructure\DocumentRepository;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Renders the document upload and status dashboard.
 */
final readonly class Action
{
    /**
     * @param WebViewRenderer $viewRenderer Yii view renderer.
     * @param DocumentRepository $repository Document persistence gateway.
     * @param string $queueDriver Active queue driver name.
     * @param int $workers Configured queue worker count.
     * @param string $extractorAdapter Active document extractor adapter.
     * @param string $llmAdapter Active LLM adapter.
     * @param string $llmModel Active LLM model name.
     * @param string $storageDriver Active document storage driver.
     */
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private DocumentRepository $repository,
        private string $queueDriver,
        private int $workers,
        private string $extractorAdapter,
        private string $llmAdapter,
        private string $llmModel,
        private string $storageDriver,
    ) {}

    /**
     * Renders the dashboard with current documents.
     */
    public function __invoke(): ResponseInterface
    {
        return $this->viewRenderer->render(
            __DIR__ . '/template',
            [
                'documents' => $this->repository->all(),
                'queueDriver' => $this->queueDriver,
                'workers' => $this->queueDriver === 'sync' ? 0 : $this->workers,
                'extractorAdapter' => $this->extractorAdapter,
                'llmAdapter' => $this->llmAdapter,
                'llmModel' => $this->llmModel,
                'storageDriver' => $this->storageDriver,
                'errors' => [],
            ],
        );
    }
}
