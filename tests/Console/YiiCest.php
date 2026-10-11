<?php

declare(strict_types=1);

namespace App\Tests\Console;

use App\Tests\Support\ConsoleTester;

final class YiiCest
{
    public function base(ConsoleTester $I): void
    {
        $I->runApp();
        $I->canSeeResultCodeIs(0);
        $I->seeInShellOutput('Yii Console');
    }

    public function runDefaultQueue(ConsoleTester $I): void
    {
        $I->runApp('queue:run yii-queue');
        $I->canSeeResultCodeIs(0);
        $I->seeInShellOutput('Processing queue yii-queue... Messages processed: 0.');
    }

    public function runDocumentQueue(ConsoleTester $I): void
    {
        $I->runApp('queue:run document-demo');
        $I->canSeeResultCodeIs(0);
        $I->seeInShellOutput('Processing queue document-demo... Messages processed: 0.');
    }
}
