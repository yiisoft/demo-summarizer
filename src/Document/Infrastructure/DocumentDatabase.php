<?php

declare(strict_types=1);

namespace App\Document\Infrastructure;

use PDO;
use RuntimeException;

use function dirname;
use function is_dir;
use function mkdir;
use function str_starts_with;
use function substr;

final class DocumentDatabase
{
    private ?PDO $pdo = null;

    public function __construct(
        private readonly string $dsn,
    ) {}

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            if (str_starts_with($this->dsn, 'sqlite:')) {
                $path = substr($this->dsn, 7);
                $directory = dirname($path);
                if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                    throw new RuntimeException("Unable to create database directory \"$directory\".");
                }
            }

            $this->pdo = new PDO($this->dsn);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }

        return $this->pdo;
    }
}
