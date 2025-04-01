<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\SSHKeyDeployer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:ssh-key:deploy',
    description: 'Deploy SSH keys to all hosts'
)]
class SSHKeyDeployCommand extends Command
{
    public function __construct(
        private readonly SSHKeyDeployer $keyDeployer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('SSH Key Deployment');

        try {
            $io->section('Starting key deployment...');
            $this->keyDeployer->deployKeys();
            $io->success('SSH keys deployed successfully.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error during key deployment: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
