<?php

declare(strict_types=1);

use App\Console;

return [
    'document:migrate' => Console\Document\MigrateCommand::class,
    'document:work' => Console\Document\WorkCommand::class,
    'hello' => Console\HelloCommand::class,
];
