<?php

declare(strict_types=1);

namespace App\Console\Document;

use App\Document\Infrastructure\DocumentRepository;
use App\Document\Processing\DocumentProcessor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\Yii\Console\ExitCode;

#[AsCommand(
    name: 'document:work',
    description: 'Process queued document summarizer jobs.',
)]
final class WorkCommand extends Command
{
    public function __construct(
        private readonly DocumentRepository $repository,
        private readonly DocumentProcessor $processor,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum queued documents to process.', 20);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = max(1, (int) $input->getOption('limit'));
        $processed = 0;

        foreach ($this->repository->queued($limit) as $document) {
            $output->writeln('Processing document #' . $document->id . ' ' . $document->originalName);
            $this->processor->process($document->id);
            $processed++;
        }

        $output->writeln("Processed $processed document(s).");

        return ExitCode::OK;
    }
}
