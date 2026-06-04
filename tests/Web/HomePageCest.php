<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Tests\Support\WebTester;

use function extension_loaded;

final class HomePageCest
{
    public function base(WebTester $I): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $I->comment('Skipped because pdo_sqlite is not installed in the host PHP runtime.');
            return;
        }

        $I->wantTo('home page works.');
        $I->amOnPage('/');
        $I->expectTo('see page home.');
        $I->see('Document Summarizer');
    }
}
