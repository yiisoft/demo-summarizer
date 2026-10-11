<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Environment;
use HttpSoft\Message\ServerRequest;
use Testo\Assert;
use Testo\Test;
use Yiisoft\Yii\Runner\Http\HttpApplicationRunner;

#[Test]
final class HomePageTest
{
    public function base(): void
    {
        $runner = new HttpApplicationRunner(
            rootPath: dirname(__DIR__, 2),
            environment: Environment::appEnv(),
        );
        $response = $runner->runAndGetResponse(new ServerRequest(uri: '/'));
        $response->getBody()->rewind();
        Assert::same($response->getStatusCode(), 200);
        Assert::string($response->getBody()->getContents())->contains('Document Summarizer');
    }
}
