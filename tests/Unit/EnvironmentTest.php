<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Environment;
use Testo\Assert;
use Testo\Lifecycle\BeforeTest;
use Testo\Lifecycle\AfterTest;
use Testo\Test;
use RuntimeException;

#[Test]
final class EnvironmentTest
{
    /** @var array<string, string|false> */
    private array $originalEnv = [];

    #[BeforeTest]
    public function setUp(): void
    {
        foreach (['APP_ENV', 'APP_DEBUG', 'APP_HOST_PATH'] as $key) {
            $this->originalEnv[$key] = getenv($key);
        }
    }

    #[AfterTest]
    public function tearDown(): void
    {
        foreach ($this->originalEnv as $key => $value) {
            if ($value === false) {
                putenv($key);
                unset($_ENV[$key]);
            } else {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
        Environment::prepare();
    }

    public function testAppEnvIsReadFromEnvironment(): void
    {
        $this->setEnv('APP_ENV', 'test');

        Assert::same(Environment::appEnv(), 'test');
    }

    public function testAppEnvDefaultsToProdWhenNotSet(): void
    {
        $this->unsetEnv('APP_ENV');

        Assert::same(Environment::appEnv(), 'prod');
    }

    public function testAppEnvDefaultsToProdWhenEmpty(): void
    {
        $this->setEnv('APP_ENV', '');

        Assert::same(Environment::appEnv(), 'prod');
    }

    public function testInvalidAppEnvThrows(): void
    {
        $this->unsetEnv('APP_ENV');
        putenv('APP_ENV=staging');

        try {
            Environment::prepare();
            Assert::fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            Assert::same($e->getMessage(), 'APP_ENV="staging" is invalid. Valid values are "dev", "test", "prod".');
        } finally {
            putenv('APP_ENV');
        }
    }

    public function testIsDev(): void
    {
        $this->setEnv('APP_ENV', 'dev');

        Assert::true(Environment::isDev());
        Assert::false(Environment::isTest());
        Assert::false(Environment::isProd());
    }

    public function testIsTest(): void
    {
        $this->setEnv('APP_ENV', 'test');

        Assert::true(Environment::isTest());
        Assert::false(Environment::isDev());
        Assert::false(Environment::isProd());
    }

    public function testIsProd(): void
    {
        $this->setEnv('APP_ENV', 'prod');

        Assert::true(Environment::isProd());
        Assert::false(Environment::isDev());
        Assert::false(Environment::isTest());
    }

    public function testAppDebugDefaultsToFalse(): void
    {
        $this->unsetEnv('APP_DEBUG');

        Assert::false(Environment::appDebug());
    }

    public function testAppDebugTrue(): void
    {
        $this->setEnv('APP_DEBUG', 'true');

        Assert::true(Environment::appDebug());
    }

    public function testAppDebugFalse(): void
    {
        $this->setEnv('APP_DEBUG', 'false');

        Assert::false(Environment::appDebug());
    }

    public function testAppHostPathDefaultsToNull(): void
    {
        $this->unsetEnv('APP_HOST_PATH');

        Assert::null(Environment::appHostPath());
    }

    public function testAppHostPathIsRead(): void
    {
        $this->setEnv('APP_HOST_PATH', '/projects/myapp');

        Assert::same(Environment::appHostPath(), '/projects/myapp');
    }

    public function testAppHostPathEmptyStringTreatedAsNull(): void
    {
        $this->setEnv('APP_HOST_PATH', '');

        Assert::null(Environment::appHostPath());
    }

    private function setEnv(string $key, string $value): void
    {
        putenv("$key=$value");
        $_ENV[$key] = $value;
        Environment::prepare();
    }

    private function unsetEnv(string $key): void
    {
        putenv($key);
        unset($_ENV[$key]);
        Environment::prepare();
    }
}
