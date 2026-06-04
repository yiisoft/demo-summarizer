<?php

declare(strict_types=1);

namespace App\Web\Document;

use App\Document\Infrastructure\DocumentRepository;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

use function array_reverse;

/**
 * Renders the document detail page and processing timeline.
 */
final readonly class DetailAction
{
    /**
     * @param CurrentRoute $currentRoute Current route with the document identifier.
     * @param DocumentRepository $repository Document persistence gateway.
     * @param WebViewRenderer $viewRenderer Yii view renderer.
     */
    public function __construct(
        private CurrentRoute $currentRoute,
        private DocumentRepository $repository,
        private WebViewRenderer $viewRenderer,
    ) {}

    /**
     * Renders document details and timeline events.
     */
    public function __invoke(): ResponseInterface
    {
        $id = (int) $this->currentRoute->getArgument('id');

        return $this->viewRenderer->render(
            __DIR__ . '/detail',
            [
                'document' => $this->repository->get($id),
                'events' => array_reverse($this->repository->events($id)),
            ],
        );
    }
}
