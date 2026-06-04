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
     */
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private DocumentRepository $repository,
        private string $queueDriver,
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
                'errors' => [],
            ],
        );
    }
}
