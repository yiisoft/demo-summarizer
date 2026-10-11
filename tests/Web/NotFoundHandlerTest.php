<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Tests\Support\WebTestCase;
use Testo\Assert;
use Testo\Test;

#[Test]
final class NotFoundHandlerTest extends WebTestCase
{
    public function nonExistentPage(): void
    {
        $this->open('/non-existent-page');
        Assert::same($this->browser->getResponse()->getStatusCode(), 404);
        $this->see('404');
        $this->see('The page /non-existent-page not found.');
        $this->see('The above error occurred while the Web server was processing your request.');
        $this->see('Please contact us if you think this is a server error. Thank you.');
    }

    public function returnHome(): void
    {
        $this->open('/non-existent-page');
        Assert::same($this->browser->getResponse()->getStatusCode(), 404);
        $this->browser->clickLink('Go Back Home');
        $this->see('Document Summarizer');
        $this->seePath('/');
    }
}
