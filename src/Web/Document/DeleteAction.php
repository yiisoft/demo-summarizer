<?php

declare(strict_types=1);

namespace App\Web\Document;

use App\Document\Infrastructure\DocumentRepository;
use App\Document\Infrastructure\DocumentStorageInterface;
use HttpSoft\Message\Response;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * Deletes a document record and its stored blobs.
 */
final readonly class DeleteAction
{
    /**
     * @param CurrentRoute $currentRoute Current route with the document identifier.
     * @param DocumentRepository $repository Document persistence gateway.
     * @param DocumentStorageInterface $storage Document blob storage.
     * @param UrlGeneratorInterface $urlGenerator Yii route URL generator.
     */
    public function __construct(
        private CurrentRoute $currentRoute,
        private DocumentRepository $repository,
        private DocumentStorageInterface $storage,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * Deletes the selected document and redirects to the dashboard.
     */
    public function __invoke(): ResponseInterface
    {
        $document = $this->repository->get((int) $this->currentRoute->getArgument('id'));
        $this->storage->delete($document->storageKey);
        if ($document->markdownKey !== null) {
            $this->storage->delete($document->markdownKey);
        }
        $this->repository->delete($document->id);

        return new Response(303, ['Location' => $this->urlGenerator->generate('home')]);
    }
}
