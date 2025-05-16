<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Host;
use App\Enum\HostConnectionStatus;
use App\Repository\HostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

/**
 * Service for checking SSH connection status of hosts.
 */
class HostConnectionChecker
{
    private const SSH_CONNECTION_OPTIONS = [
        'StrictHostKeyChecking=no',
        'BatchMode=yes',
        'PasswordAuthentication=no',
    ];

    public function __construct(
        private readonly HostRepository $hostRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly string $keyRollPrivateKeyPath,
        private readonly int $connectionTimeout,
    ) {
    }

    /**
     * Checks and updates the connection status for a single host.
     * The change is persisted but not flushed immediately.
     */
    public function checkAndUpdateHostStatus(Host $host): void
    {
        $this->logger->info(sprintf('Checking connection for host: %s (%s)', $host->getName(), $host->getHostname()));

        $process = $this->createSshTestProcess($host);

        try {
            $process->mustRun();

            if ($process->isSuccessful()) {
                $host->setConnectionStatus(HostConnectionStatus::SUCCESSFUL);
                $this->logger->info(sprintf('Connection successful for host: %s', $host->getName()));
            }
        } catch (\Symfony\Component\Process\Exception\ProcessFailedException $e) {
            $host->setConnectionStatus(HostConnectionStatus::FAILED);
            $this->logger->warning(
                sprintf(
                    'Connection failed for host %s (%s) via ProcessFailedException: %s. Stderr: %s',
                    $host->getName(),
                    $host->getHostname(),
                    $e->getMessage(),
                    $process->getErrorOutput()
                )
            );
        } catch (\Exception $e) {
            $host->setConnectionStatus(HostConnectionStatus::FAILED);
            $this->logger->error(
                sprintf(
                    'An unexpected error occurred while checking host %s (%s): %s',
                    $host->getName(),
                    $host->getHostname(),
                    $e->getMessage()
                )
            );
        }

        $this->entityManager->persist($host);
    }

    /**
     * Checks and updates the connection status for all hosts.
     * Flushes changes to the database once after checking all hosts.
     */
    public function checkAllHostsConnections(): void
    {
        $this->logger->info('Starting connection checks for all hosts.');
        $hosts = $this->hostRepository->findAll();

        if (empty($hosts)) {
            $this->logger->info('No hosts found to check.');

            return;
        }

        foreach ($hosts as $host) {
            $this->checkAndUpdateHostStatus($host);
        }

        try {
            $this->entityManager->flush();
            $this->logger->info('Finished connection checks for all hosts and flushed changes.');
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Failed to flush host status updates: %s', $e->getMessage()));
        }
    }

    /**
     * Creates an SSH Process instance for testing connectivity to the given host.
     */
    private function createSshTestProcess(Host $host): Process
    {
        $sshFinalOptions = array_merge(
            self::SSH_CONNECTION_OPTIONS,
            ["ConnectTimeout={$this->connectionTimeout}"]
        );

        $command = [
            'ssh',
            '-i',
            $this->keyRollPrivateKeyPath,
            '-p',
            (string) $host->getPort(),
        ];

        foreach ($sshFinalOptions as $option) {
            $command[] = '-o';
            $command[] = $option;
        }

        $command[] = sprintf('%s@%s', $host->getUsername(), $host->getHostname());
        $command[] = 'exit';

        $process = new Process($command);
        $process->setTimeout($this->connectionTimeout + 5);

        return $process;
    }
}
