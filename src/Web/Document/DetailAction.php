<?php

declare(strict_types=1);

namespace App\Web\Document;

use App\Document\Infrastructure\DocumentRepository;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Renders the document detail page and processing timeline.
 */
final readonly class DetailAction
{
    public function __construct(
        private CurrentRoute $currentRoute,
        private DocumentRepository $repository,
        private WebViewRenderer $viewRenderer,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $id = (int) $this->currentRoute->getArgument('id');

        return $this->viewRenderer->render(
            __DIR__ . '/detail',
            [
                'document' => $this->repository->get($id),
                'events' => $this->repository->events($id),
            ],
        );
    }
}
