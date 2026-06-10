<?php

declare(strict_types=1);

namespace App\Web\Document;

use App\Document\Infrastructure\DocumentRepository;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Router\HydratorAttribute\RouteArgument;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

use function array_reverse;

/**
 * Renders the document detail page and processing timeline.
 */
final readonly class DetailAction
{
    /**
     * @param DocumentRepository $repository Document persistence gateway.
     * @param WebViewRenderer $viewRenderer Yii view renderer.
     */
    public function __construct(
        private DocumentRepository $repository,
        private WebViewRenderer $viewRenderer,
    ) {}

    /**
     * Renders document details and timeline events.
     */
    public function __invoke(#[RouteArgument] int $id): ResponseInterface
    {
        return $this->viewRenderer->render(
            __DIR__ . '/detail',
            [
                'document' => $this->repository->get($id),
                'events' => array_reverse($this->repository->events($id)),
            ],
        );
    }
}
