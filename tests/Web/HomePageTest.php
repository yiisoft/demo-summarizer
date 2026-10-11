<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Tests\Support\WebTestCase;
use Testo\Assert;
use Testo\Test;

#[Test]
final class HomePageTest extends WebTestCase
{
    public function base(): void
    {
        $this->open('/');
        Assert::same($this->browser->getResponse()->getStatusCode(), 200);
        $this->see('Document Summarizer');
    }
}
