<?php

declare(strict_types=1);

namespace App\Web\Document;

use App\Document\Processing\DocumentClearService;
use HttpSoft\Message\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Clears all document records, stored blobs, and pending queue jobs.
 */
final readonly class ClearAction
{
    public function __construct(
        private DocumentClearService $clearService,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $this->clearService->clear();

        return new Response(303, ['Location' => '/']);
    }
}
