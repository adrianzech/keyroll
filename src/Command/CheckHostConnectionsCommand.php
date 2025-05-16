<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\HostConnectionChecker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:host:check-connections',
    description: 'Checks SSH connectivity to all registered hosts and updates their status.',
)]
class CheckHostConnectionsCommand extends Command
{
    public function __construct(
        private readonly HostConnectionChecker $hostConnectionChecker,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Host Connection Check');

        $io->note('Checking connections for all hosts...');
        $this->hostConnectionChecker->checkAllHostsConnections();
        $io->success('All host connection statuses have been checked and updated.');

        return Command::SUCCESS;
    }
}
