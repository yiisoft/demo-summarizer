<?php

declare(strict_types=1);

namespace App\Web\HomePage;

use App\Document\Infrastructure\DocumentRepository;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private DocumentRepository $repository,
        private string $queueDriver,
    ) {}

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
