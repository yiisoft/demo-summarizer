<?php

declare(strict_types=1);

namespace App\Web\NotFound;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Http\Status;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Renders the demo 404 page for unmatched routes.
 */
final readonly class NotFoundHandler implements RequestHandlerInterface
{
    /**
     * @param UrlGeneratorInterface $urlGenerator URL generator passed to the view.
     * @param CurrentRoute $currentRoute Current route passed to the view.
     * @param WebViewRenderer $viewRenderer Yii view renderer.
     */
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private CurrentRoute $currentRoute,
        private WebViewRenderer $viewRenderer,
    ) {}

    /**
     * Renders a 404 response for unmatched requests.
     *
     * @param ServerRequestInterface $request Current server request.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->viewRenderer
            ->render(__DIR__ . '/template', ['urlGenerator' => $this->urlGenerator, 'currentRoute' => $this->currentRoute])
            ->withStatus(Status::NOT_FOUND);
    }
}
