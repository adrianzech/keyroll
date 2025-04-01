<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'app:ssh-key:generate',
    description: 'Generate the SSH key that KeyRoll will use to connect to hosts',
)]
class SSHKeyGenerateCommand extends Command
{
    public function __construct(
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('KeyRoll Public SSH Key Generation');

        $keyDir = $this->projectDir . '/var/ssh';
        $keyPath = $keyDir . '/keyroll_ed25519';

        $fs = new Filesystem();

        // Create directory if it doesn't exist
        if (!$fs->exists($keyDir)) {
            $fs->mkdir($keyDir);
            $io->success('Created directory: ' . $keyDir);
        }

        if ($fs->exists($keyPath)) {
            $io->note('SSH Key already exists at ' . $keyPath);

            if (!$io->confirm('Do you want to regenerate it? This will overwrite the existing key.', false)) {
                $io->success('Operation cancelled. Using existing key.');

                return Command::SUCCESS;
            }
        }

        // Generate SSH key
        $io->section('Generating SSH key pair...');

        // If key exists and user confirmed overwrite, remove the files first
        if ($fs->exists($keyPath)) {
            $fs->remove([$keyPath, $keyPath . '.pub']);
        }

        // Generate new key
        $process = new Process([
            'ssh-keygen',
            '-t',
            'ed25519',
            '-f',
            $keyPath,
            '-N',
            '', // Empty passphrase
            '-C',
            'KeyRoll',
            '-q', // Quiet mode
        ]);

        $process->run(function ($type, $buffer) use ($io) {
            $io->write($buffer);
        });

        if (!$process->isSuccessful()) {
            $io->error('Failed to generate SSH key: ' . $process->getErrorOutput());

            return Command::FAILURE;
        }

        // Set proper permissions
        $fs->chmod($keyPath, 0600);
        $fs->chmod($keyPath . '.pub', 0644);
        $io->success('SSH key pair generated successfully at ' . $keyPath);

        // Show public key
        $publicKey = file_get_contents($keyPath . '.pub');
        $io->section('Public Key:');
        $io->writeln($publicKey);

        $io->success('KeyRoll SSH key setup completed successfully.');
        $io->note('Make sure to add this public key to all your servers authorized_keys files.');

        return Command::SUCCESS;
    }
}
