<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

use function in_array;
use function sprintf;

/**
 * Reads and normalizes process environment flags used during application bootstrap.
 */
final class Environment
{
    public const DEV = 'dev';
    public const TEST = 'test';
    public const PROD = 'prod';

    public const ENVIRONMENTS = [
        self::DEV,
        self::TEST,
        self::PROD,
    ];

    private static array $values = [];

    /**
     * Loads supported environment variables into normalized in-memory values.
     */
    public static function prepare(): void
    {
        self::setEnvironment();
        self::setBoolean('APP_C3', false);
        self::setBoolean('APP_DEBUG', false);
        self::setNonEmptyStringOrNull('APP_HOST_PATH', null);
    }

    /**
     * Returns the current application environment.
     *
     * @return non-empty-string
     */
    public static function appEnv(): string
    {
        /** @var non-empty-string */
        return self::$values['APP_ENV'];
    }

    /**
     * Returns whether the application runs in development mode.
     */
    public static function isDev(): bool
    {
        return self::appEnv() === self::DEV;
    }

    /**
     * Returns whether the application runs in test mode.
     */
    public static function isTest(): bool
    {
        return self::appEnv() === self::TEST;
    }

    /**
     * Returns whether the application runs in production mode.
     */
    public static function isProd(): bool
    {
        return self::appEnv() === self::PROD;
    }

    /**
     * Returns the host-side application path, when configured.
     *
     * @return non-empty-string|null
     */
    public static function appHostPath(): ?string
    {
        /** @var non-empty-string|null */
        return self::$values['APP_HOST_PATH'];
    }

    /**
     * Returns whether Codeception coverage support is enabled.
     */
    public static function appC3(): bool
    {
        /** @var bool */
        return self::$values['APP_C3'];
    }

    /**
     * Returns whether application debug mode is enabled.
     */
    public static function appDebug(): bool
    {
        /** @var bool */
        return self::$values['APP_DEBUG'];
    }

    /**
     * Validates and stores the APP_ENV value.
     */
    private static function setEnvironment(): void
    {
        $environment = self::getRawValue('APP_ENV') ?: self::PROD;

        if (!in_array($environment, self::ENVIRONMENTS, true)) {
            throw new RuntimeException(
                sprintf(
                    'APP_ENV="%s" is invalid. Valid values are "%s".',
                    $environment,
                    implode('", "', self::ENVIRONMENTS),
                ),
            );
        }

        self::$values['APP_ENV'] = $environment;
    }

    /**
     * Stores a boolean environment value.
     *
     * @param string $key Environment variable name.
     * @param bool $default Default value when the variable is absent or invalid.
     */
    private static function setBoolean(string $key, bool $default): void
    {
        $value = self::getRawValue($key);
        self::$values[$key] = $value === null
            ? $default
            : (filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default);
    }

    /**
     * Stores an integer environment value.
     *
     * @param string $key Environment variable name.
     * @param int $default Default value when the variable is absent.
     */
    private static function setInteger(string $key, int $default): void
    {
        $value = self::getRawValue($key);
        self::$values[$key] = $value === null ? $default : (int) $value;
    }

    /**
     * Stores a string environment value.
     *
     * @param string $key Environment variable name.
     * @param string $default Default value when the variable is absent.
     */
    private static function setString(string $key, string $default): void
    {
        $value = self::getRawValue($key);
        self::$values[$key] = $value ?? $default;
    }

    /**
     * Stores a non-empty string value or null.
     *
     * @param string $key Environment variable name.
     * @param non-empty-string|null $default Default value when the variable is absent or empty.
     */
    private static function setNonEmptyStringOrNull(string $key, ?string $default): void
    {
        $value = self::getRawValue($key);
        self::$values[$key] = $value === null || $value === '' ? $default : $value;
    }

    /**
     * Reads a raw environment value from server and process sources.
     *
     * @param string $key Environment variable name.
     */
    private static function getRawValue(string $key): ?string
    {
        $value = getenv($key, true);
        if ($value !== false) {
            return $value;
        }

        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return isset($_ENV[$key]) ? (string) $_ENV[$key] : null;
    }
}
