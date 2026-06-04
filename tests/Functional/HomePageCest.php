<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\FunctionalTester;
use HttpSoft\Message\ServerRequest;

use function extension_loaded;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

final class HomePageCest
{
    public function base(FunctionalTester $tester): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $tester->comment('Skipped because pdo_sqlite is not installed in the host PHP runtime.');
            return;
        }

        $response = $tester->sendRequest(
            new ServerRequest(uri: '/'),
        );

        assertSame(200, $response->getStatusCode());
        assertStringContainsString(
            'Document Summarizer',
            $response->getBody()->getContents(),
        );
    }
}
