<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Entity\Host;
use App\Entity\SSHKey;
use App\Entity\User;
use App\Enum\HostConnectionStatus;
use App\Repository\HostRepository;
use App\Repository\SSHKeyRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class SSHKeyDeployer
{
    private const CONNECTION_OPTIONS = [
        'StrictHostKeyChecking=no',
        'BatchMode=yes',
        'PasswordAuthentication=no',
    ];

    private const KEYROLL_MARKER = "\n#################### KeyRoll ####################\n";

    public function __construct(
        private readonly HostRepository $hostRepository,
        private readonly SSHKeyRepository $sshKeyRepository,
        private readonly LoggerInterface $logger,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $keyRollPrivateKeyPath,
        private readonly int $connectionTimeout = 10,
    ) {
    }

    private function isValidKey(string $key): bool
    {
        return $key !== '' && !str_starts_with($key, '#');
    }

    public function deployKeys(): void
    {
        $hosts = $this->hostRepository->findAll();

        if (empty($hosts)) {
            $this->logger->info('SSHKeyDeployer: No hosts found to deploy keys to.');

            return;
        }
        $this->logger->info(sprintf('SSHKeyDeployer: Starting key deployment for %d host(s).', count($hosts)));

        foreach ($hosts as $host) {
            try {
                $host->setConnectionStatus(HostConnectionStatus::CHECKING);
                $this->entityManager->persist($host);

                $this->logger->info(
                    sprintf('SSHKeyDeployer: Deploying keys to host %s (%s)', $host->getName(), $host->getHostname())
                );
                $this->deployKeysToHost($host);
            } catch (ProcessFailedException $e) {
                $this->logger->error(
                    'SSHKeyDeployer: Failed to deploy keys to host {host_name} due to ProcessFailedException (already handled, continuing): {error_message}',
                    ['host_name' => $host->getName(), 'error_message' => $e->getMessage()]
                );
            } catch (\Exception $e) {
                $this->logger->error(
                    'SSHKeyDeployer: An unexpected error occurred for host {host_name} during deployKeys loop: {error_message}',
                    ['host_name' => $host->getName(), 'error_message' => $e->getMessage(), 'exception' => $e]
                );
                if ($host->getConnectionStatus() !== HostConnectionStatus::FAILED) {
                    $host->setConnectionStatus(HostConnectionStatus::FAILED);
                    $this->entityManager->persist($host);
                }
            }
        }

        try {
            $this->entityManager->flush();
            $this->logger->info('SSHKeyDeployer: Finished key deployment process and flushed all changes.');
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('SSHKeyDeployer: Failed to flush changes after deployment: %s', $e->getMessage())
            );
        }
    }

    /**
     * Determines the list of public SSH key strings that should be deployed to a given host
     * based on its categories and associated users.
     *
     * @return array<int, string> list of public key strings
     */
    private function determinePublicKeysToDeploy(Host $host): array
    {
        $hostCategories = $host->getCategories();
        if ($hostCategories->isEmpty()) {
            $this->logger->info(
                'Host {host} has no categories assigned. No KeyRoll keys will be deployed from categories.',
                [
                    'host' => $host->getName(),
                ]
            );

            return [];
        }

        $usersToDeploy = $this->getUsersForCategories($hostCategories);
        if (empty($usersToDeploy)) {
            $this->logger->info(
                'No users found for categories on host {host}. No KeyRoll keys will be deployed from categories.',
                ['host' => $host->getName()]
            );

            return [];
        }

        $sshKeysOfUsers = $this->sshKeyRepository->findBy(['user' => $usersToDeploy]);
        if (empty($sshKeysOfUsers)) {
            $this->logger->info(
                'No SSH keys found for users associated with categories of host {host}. No KeyRoll keys will be deployed from categories.',
                ['host' => $host->getName()]
            );

            return [];
        }

        return array_map(static fn (SSHKey $key): string => $key->getPublicKey(), $sshKeysOfUsers);
    }

    private function deployKeysToHost(Host $host): void
    {
        try {
            $this->verifyHostConnection($host);

            $existingKeysOnHost = $this->getExistingAuthorizedKeys($host);
            $publicKeysToDeploy = $this->determinePublicKeysToDeploy($host);
            $mergedKeysContent = $this->mergeKeys($existingKeysOnHost, $publicKeysToDeploy);

            if ($mergedKeysContent === $existingKeysOnHost) {
                $this->logger->info(
                    'No changes needed for host {host}; authorized_keys file is already in the desired state.',
                    ['host' => $host->getName()]
                );
                $host->setConnectionStatus(HostConnectionStatus::SUCCESSFUL);
                $this->entityManager->persist($host);

                return;
            }

            $this->updateAuthorizedKeysFile($host, $mergedKeysContent);

            $host->setConnectionStatus(HostConnectionStatus::SUCCESSFUL);
            $this->entityManager->persist($host);
        } catch (ProcessFailedException $e) {
            $this->logger->error(
                'Key deployment process failed for host {host_name}: {error_message}. Stderr: {stderr}',
                [
                    'host_name' => $host->getName(),
                    'error_message' => $e->getMessage(),
                    'stderr' => $e->getProcess()->getErrorOutput(),
                ]
            );
            $host->setConnectionStatus(HostConnectionStatus::FAILED);
            $this->entityManager->persist($host);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error during key deployment to host {host_name}: {error_message}', [
                'host_name' => $host->getName(),
                'error_message' => $e->getMessage(),
                'exception' => $e,
            ]);
            $host->setConnectionStatus(HostConnectionStatus::FAILED);
            $this->entityManager->persist($host);
            throw $e;
        }
    }

    /**
     * @param Collection<int, Category> $categories
     *
     * @return array<int, User>
     */
    private function getUsersForCategories(Collection $categories): array
    {
        $uniqueUsers = [];
        $processedUserIds = [];

        foreach ($categories as $category) {
            foreach ($category->getUsers() as $user) {
                $userId = $user->getId();
                if ($userId !== null && !isset($processedUserIds[$userId])) {
                    $uniqueUsers[] = $user;
                    $processedUserIds[$userId] = true;
                }
            }
        }

        return $uniqueUsers;
    }

    private function updateAuthorizedKeysFile(Host $host, string $mergedKeysContent): void
    {
        $tempFilePath = tempnam(sys_get_temp_dir(), 'keyroll_auth_keys_');
        if ($tempFilePath === false) {
            throw new \RuntimeException('Failed to create temporary file for authorized_keys.');
        }

        try {
            file_put_contents($tempFilePath, $mergedKeysContent);
            $this->deployFileUsingScp($host, $tempFilePath);

            $keyCount = count(array_filter(explode("\n", $mergedKeysContent), $this->isValidKey(...)));
            $this->logger->info(
                'Successfully deployed {keyCount} keys to host {host_name}. authorized_keys file updated.',
                [
                    'host_name' => $host->getName(),
                    'keyCount' => $keyCount,
                ]
            );
        } finally {
            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }
        }
    }

    private function verifyHostConnection(Host $host): void
    {
        $this->logger->debug('Verifying host connection for {host_name}', ['host_name' => $host->getHostname()]);
        $process = $this->createSshProcess(
            $host,
            ['echo "KeyRoll connection test successful"']
        );
        $process->mustRun();
        $this->logger->debug('Host connection verified for {host_name}', ['host_name' => $host->getHostname()]);
    }

    private function getExistingAuthorizedKeys(Host $host): string
    {
        $process = $this->createSshProcess(
            $host,
            ['cat ~/.ssh/authorized_keys 2>/dev/null || echo ""']
        );
        $process->run();

        if (!$process->isSuccessful()) {
            $this->logger->warning(
                'Could not reliably retrieve authorized_keys from {host_name}. Assuming empty or inaccessible. Error: {error}',
                ['host_name' => $host->getName(), 'error' => $process->getErrorOutput()]
            );

            return '';
        }

        return trim($process->getOutput());
    }

    /**
     * @param array<int, string> $targetPublicKeys
     */
    private function mergeKeys(string $existingKeysString, array $targetPublicKeys): string
    {
        $existingKeysString = str_replace("\r\n", "\n", $existingKeysString);
        $lines = $existingKeysString ? explode("\n", $existingKeysString) : [];

        $processedTargetKeys = array_filter(
            array_map('trim', $targetPublicKeys),
            $this->isValidKey(...)
        );
        $processedTargetKeys = array_unique($processedTargetKeys);

        $finalKeyLines = [];
        $inKeyRollManagedSection = false;
        $keyRollMarkerFound = false;

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            if ($trimmedLine === trim(self::KEYROLL_MARKER)) {
                if (!$keyRollMarkerFound) {
                    $inKeyRollManagedSection = true;
                    $keyRollMarkerFound = true;
                    continue;
                }
                $inKeyRollManagedSection = false;
                continue;
            }

            if (!$inKeyRollManagedSection) {
                if ($this->isValidKey($trimmedLine)) {
                    $finalKeyLines[] = $trimmedLine;
                }
            }
        }

        $finalKeyLines[] = trim(self::KEYROLL_MARKER);

        foreach ($processedTargetKeys as $key) {
            $finalKeyLines[] = $key;
        }

        $finalKeyLines = array_unique($finalKeyLines);
        $finalKeyLines = array_filter($finalKeyLines, static fn (string $key): bool => $key !== '');

        return implode("\n", $finalKeyLines);
    }

    private function deployFileUsingScp(Host $host, string $localTempFilePath): void
    {
        $remoteSSHPath = '~/.ssh';
        $remoteAuthorizedKeysPath = $remoteSSHPath . '/authorized_keys';

        $this->createSshProcess($host, ["mkdir -p {$remoteSSHPath} && chmod 700 {$remoteSSHPath}"])->mustRun();
        $this->createScpProcess($host, $localTempFilePath, $remoteAuthorizedKeysPath)->mustRun();
        $this->createSshProcess($host, ["chmod 600 {$remoteAuthorizedKeysPath}"])->mustRun();
    }

    /**
     * @param array<int, string> $commands
     */
    private function createSshProcess(Host $host, array $commands): Process
    {
        $sshConnectionOptions = array_merge(
            self::CONNECTION_OPTIONS,
            ["ConnectTimeout={$this->connectionTimeout}"]
        );

        $processCommand = ['ssh'];
        $processCommand[] = '-i';
        $processCommand[] = $this->keyRollPrivateKeyPath;

        foreach ($sshConnectionOptions as $option) {
            $processCommand[] = '-o';
            $processCommand[] = $option;
        }

        $processCommand[] = sprintf('%s@%s', $host->getUsername(), $host->getHostname());
        $processCommand[] = '-p';
        $processCommand[] = (string) $host->getPort();
        array_push($processCommand, ...$commands);

        $process = new Process($processCommand);
        $process->setTimeout($this->connectionTimeout + 15);

        return $process;
    }

    private function createScpProcess(Host $host, string $localFilePath, string $remoteTargetFilePath): Process
    {
        $scpConnectionOptions = array_merge(
            self::CONNECTION_OPTIONS,
            ["ConnectTimeout={$this->connectionTimeout}"]
        );

        $processCommand = ['scp'];
        $processCommand[] = '-i';
        $processCommand[] = $this->keyRollPrivateKeyPath;

        foreach ($scpConnectionOptions as $option) {
            $processCommand[] = '-o';
            $processCommand[] = $option;
        }

        $processCommand[] = '-P';
        $processCommand[] = (string) $host->getPort();
        $processCommand[] = $localFilePath;
        $processCommand[] = sprintf('%s@%s:%s', $host->getUsername(), $host->getHostname(), $remoteTargetFilePath);

        $process = new Process($processCommand);
        $process->setTimeout($this->connectionTimeout + 30);

        return $process;
    }
}
