<?php

declare(strict_types=1);

namespace App\Console\Document;

use App\Document\Infrastructure\DocumentSchema;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\Yii\Console\ExitCode;

#[AsCommand(
    name: 'document:migrate',
    description: 'Create or update document summarizer SQLite tables.',
)]
final class MigrateCommand extends Command
{
    public function __construct(
        private readonly DocumentSchema $schema,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->schema->migrate();
        $output->writeln('Document tables are ready.');

        return ExitCode::OK;
    }
}
