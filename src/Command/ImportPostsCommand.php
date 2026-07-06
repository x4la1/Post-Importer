<?php

namespace App\Command;

use App\Service\PostImporterService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:import-posts')]
class ImportPostsCommand extends Command
{
    public function __construct(private PostImporterService $postImporterService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'from-page',
                null,
                InputOption::VALUE_OPTIONAL,
            )
            ->addOption(
                'to-page',
                null,
                InputOption::VALUE_OPTIONAL,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fromPage = $input->getOption('from-page');
        $toPage = $input->getOption('to-page');

        $this->postImporterService->import(
            $fromPage !== null ? (int)$fromPage : null,
            $toPage !== null ? (int)$toPage : null
        );

        return Command::SUCCESS;
    }
}
