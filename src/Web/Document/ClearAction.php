<?php

declare(strict_types=1);

namespace App\Web\Document;

use App\Document\Processing\DocumentClearService;
use HttpSoft\Message\Response;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * Clears all document records, stored blobs, and pending queue jobs.
 */
final readonly class ClearAction
{
    /**
     * @param DocumentClearService $clearService Service that clears documents and queues.
     * @param UrlGeneratorInterface $urlGenerator Yii route URL generator.
     */
    public function __construct(
        private DocumentClearService $clearService,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * Clears all demo document data and redirects to the dashboard.
     */
    public function __invoke(): ResponseInterface
    {
        $this->clearService->clear();

        return new Response(303, ['Location' => $this->urlGenerator->generate('home')]);
    }
}
