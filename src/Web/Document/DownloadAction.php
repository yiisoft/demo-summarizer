<?php

declare(strict_types=1);

namespace App\Web\Document;

use App\Document\Infrastructure\DocumentRepository;
use App\Document\Infrastructure\DocumentStorageInterface;
use HttpSoft\Message\Response;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Router\CurrentRoute;

final readonly class DownloadAction
{
    public function __construct(
        private CurrentRoute $currentRoute,
        private DocumentRepository $repository,
        private DocumentStorageInterface $storage,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $document = $this->repository->get((int) $this->currentRoute->getArgument('id'));

        return new Response(
            200,
            [
                'Content-Type' => $document->mimeType,
                'Content-Disposition' => 'attachment; filename="' . addslashes($document->originalName) . '"',
            ],
            $this->storage->read($document->storageKey),
        );
    }
}
