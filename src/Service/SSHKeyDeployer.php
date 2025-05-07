<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Entity\Host;
use App\Entity\SSHKey;
use App\Entity\User;
use App\Repository\HostRepository;
use App\Repository\SSHKeyRepository;
use Doctrine\Common\Collections\Collection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Service for deploying SSH keys to remote hosts.
 */
readonly class SSHKeyDeployer
{
    private const CONNECTION_OPTIONS = [
        'StrictHostKeyChecking=no',
        'BatchMode=yes',
    ];

    // Marker used to delineate keys managed by this service in the authorized_keys file.
    private const KEYROLL_MARKER = "\n#################### KeyRoll ####################\n";

    public function __construct(
        private HostRepository $hostRepository,
        private SSHKeyRepository $sshKeyRepository,
        private LoggerInterface $logger,
        private string $keyRollPrivateKeyPath,
        private int $connectionTimeout = 10,
    ) {
    }

    /**
     * Filter for valid SSH key lines.
     * A valid key line is non-empty and does not start with a hash '#'.
     */
    private function isValidKey(string $key): bool
    {
        return $key !== '' && !str_starts_with($key, '#');
    }

    /**
     * Deploys SSH keys to all active hosts.
     */
    public function deployKeys(): void
    {
        $hosts = $this->hostRepository->findAll();

        foreach ($hosts as $host) {
            try {
                $this->deployKeysToHost($host);
            } catch (\Exception $e) {
                $this->logger->error('Failed to deploy keys to host {host}: {error}', [
                    'host' => $host->getName(),
                    'error' => $e->getMessage(),
                    'exception' => $e, // Includes stack trace for detailed debugging
                ]);
            }
        }
    }

    /**
     * Deploys SSH keys to a specific host.
     * This involves fetching users and their keys based on host categories,
     * then merging these keys with existing ones on the remote host.
     */
    private function deployKeysToHost(Host $host): void
    {
        $this->verifyHostConnection($host);

        $hostCategories = $host->getCategories();
        if ($hostCategories->isEmpty()) {
            $this->logger->info('Host {host} has no categories assigned. Skipping key deployment.', [
                'host' => $host->getName(),
            ]);

            $this->updateAuthorizedKeysFile($host, $this->mergeKeys($this->getExistingAuthorizedKeys($host), []));

            return;
        }

        $usersToDeploy = $this->getUsersForCategories($hostCategories);
        if (empty($usersToDeploy)) {
            $this->logger->info('No users found for the categories assigned to host {host}. Skipping key deployment.', [
                'host' => $host->getName(),
            ]);

            $this->updateAuthorizedKeysFile($host, $this->mergeKeys($this->getExistingAuthorizedKeys($host), []));

            return;
        }

        $sshKeysOfUsers = $this->sshKeyRepository->findBy(['user' => $usersToDeploy]);

        if (empty($sshKeysOfUsers)) {
            $this->logger->info('No active SSH keys found for users associated with categories of host {host}.', [
                'host' => $host->getName(),
            ]);
            $this->updateAuthorizedKeysFile($host, $this->mergeKeys($this->getExistingAuthorizedKeys($host), []));

            return;
        }

        $publicKeysToDeploy = array_map(static fn (SSHKey $key): string => $key->getPublicKey(), $sshKeysOfUsers);

        $existingKeysOnHost = $this->getExistingAuthorizedKeys($host);
        $mergedKeysContent = $this->mergeKeys($existingKeysOnHost, $publicKeysToDeploy);

        // Only update the file if there's an actual change in content.
        if ($mergedKeysContent === $existingKeysOnHost) {
            $this->logger->info(
                'No changes needed for host {host}; relevant keys already deployed and correctly formatted.',
                [
                    'host' => $host->getName(),
                ]
            );

            return;
        }

        $this->updateAuthorizedKeysFile($host, $mergedKeysContent);
    }

    /**
     * Finds all unique users associated with a collection of categories.
     *
     * @param Collection<int, Category> $categories the collection of categories
     *
     * @return array<int, User> an array of unique User objects
     */
    private function getUsersForCategories(Collection $categories): array
    {
        $uniqueUsers = [];
        $processedUserIds = []; // To track user IDs and ensure uniqueness

        foreach ($categories as $category) {
            foreach ($category->getUsers() as $user) {
                $userId = $user->getId();
                // Ensure user has an ID and has not been processed yet
                if ($userId !== null && !isset($processedUserIds[$userId])) {
                    $uniqueUsers[] = $user;
                    $processedUserIds[$userId] = true;
                }
            }
        }

        return $uniqueUsers;
    }

    /**
     * Updates the authorized_keys file on the remote host with the merged key content.
     * It first writes the keys to a temporary local file, then securely copies it.
     */
    private function updateAuthorizedKeysFile(Host $host, string $mergedKeysContent): void
    {
        $tempFilePath = tempnam(sys_get_temp_dir(), 'keyroll_auth_keys_');
        if ($tempFilePath === false) {
            // This would be a system-level issue, highly unlikely but good to check.
            throw new \RuntimeException('Failed to create temporary file for authorized_keys.');
        }

        try {
            file_put_contents($tempFilePath, $mergedKeysContent);
            $this->deployFileUsingScp($host, $tempFilePath);

            // Count lines effectively to report number of keys, excluding empty lines if any.
            $keyCount = count(array_filter(explode("\n", $mergedKeysContent), $this->isValidKey(...)));

            $this->logger->info('Successfully deployed {keyCount} keys to host {host}.', [
                'host' => $host->getName(),
                'keyCount' => $keyCount,
            ]);
        } finally {
            // Ensure the temporary file is always deleted.
            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }
        }
    }

    /**
     * Verifies the SSH connection to the remote host by executing a simple echo command.
     *
     * @throws ProcessFailedException if the connection fails
     */
    private function verifyHostConnection(Host $host): void
    {
        $process = $this->createSshProcess(
            $host,
            ['echo "KeyRoll connection test successful"']
        );
        $process->run();

        if (!$process->isSuccessful()) {
            $this->logger->error('Host connection verification failed for {host}: {output} {errorOutput}', [
                'host' => $host->getHostname(),
                'output' => $process->getOutput(),
                'errorOutput' => $process->getErrorOutput(),
            ]);
            throw new ProcessFailedException($process);
        }
        $this->logger->debug('Host connection verified for {host}', ['host' => $host->getHostname()]);
    }

    /**
     * Retrieves the content of the existing authorized_keys file from the remote host.
     * Returns an empty string if the file does not exist or is not readable.
     */
    private function getExistingAuthorizedKeys(Host $host): string
    {
        // The command attempts to cat the file; if it fails (e.g., file not found),
        // it outputs nothing due to 2>/dev/null. The '|| echo ""' is a fallback
        // in case 'cat' itself fails in a way that produces no output, ensuring
        // the process output is at least an empty string.
        $process = $this->createSshProcess(
            $host,
            // Ensure we get an empty string if file doesn't exist or cat fails.
            ['cat ~/.ssh/authorized_keys 2>/dev/null || echo ""']
        );
        $process->run();

        if (!$process->isSuccessful()) {
            // Log error but proceed with empty content as a failure to read
            // might mean the file doesn't exist, which is a valid state.
            $this->logger->warning(
                'Could not reliably retrieve authorized_keys from {host}. Assuming empty or inaccessible. Error: {error}',
                ['host' => $host->getName(), 'error' => $process->getErrorOutput()]
            );

            return ''; // Treat as empty if there was an issue reading it.
        }

        return trim($process->getOutput());
    }

    /**
     * Merges existing keys with new target keys, managing a specific section marked by KEYROLL_MARKER.
     * Keys outside the marker are preserved. Keys inside the marker are replaced by targetKeys.
     * The final list of keys is made unique across the entire file.
     *
     * @param string        $existingKeysString the current content of the authorized_keys file
     * @param array<string> $targetPublicKeys   an array of public key strings to be deployed
     *
     * @return string the merged content for the authorized_keys file
     */
    private function mergeKeys(string $existingKeysString, array $targetPublicKeys): string
    {
        // Normalize line endings for consistent processing
        $existingKeysString = str_replace("\r\n", "\n", $existingKeysString);
        $lines = $existingKeysString ? explode("\n", $existingKeysString) : [];

        // Prepare target keys: trim, filter invalid, ensure uniqueness
        $processedTargetKeys = array_filter(
            array_map('trim', $targetPublicKeys),
            $this->isValidKey(...)
        );
        $processedTargetKeys = array_unique($processedTargetKeys);

        $finalKeyLines = [];
        $inKeyRollManagedSection = false;

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            // Check if the current line is the KeyRoll marker
            if ($trimmedLine === trim(self::KEYROLL_MARKER)) {
                $inKeyRollManagedSection = true;
                // Do not add the marker itself here; it will be added systematically later.
                continue;
            }

            if (!$inKeyRollManagedSection) {
                // This line is outside (before) the KeyRoll managed section.
                if ($this->isValidKey($trimmedLine)) {
                    $finalKeyLines[] = $trimmedLine;
                }
            }
            // Lines within or after the old KeyRoll section are ignored,
            // as this section will be entirely replaced by $processedTargetKeys.
        }

        // Add the KeyRoll marker. This ensures it's present.
        // If existing content was empty or had no marker, it's added now.
        // After the loop, we are conceptually "after" any preserved non-KeyRoll keys.
        $finalKeyLines[] = trim(self::KEYROLL_MARKER);

        // Add all processed target keys into the managed section.
        foreach ($processedTargetKeys as $key) {
            $finalKeyLines[] = $key; // Keys are already validated and trimmed.
        }

        // Ensure all key lines in the final list are unique across the entire content.
        // This removes duplicates if a target key was also manually present outside the marker.
        $finalKeyLines = array_unique($finalKeyLines);

        // Filter out any completely empty lines that might have resulted from processing.
        $finalKeyLines = array_filter($finalKeyLines, static fn (string $key): bool => $key !== '');

        return implode("\n", $finalKeyLines);
    }

    /**
     * Deploys the local temporary authorized_keys file to the remote host using SCP.
     * This includes creating the .ssh directory and setting appropriate permissions.
     *
     * @throws ProcessFailedException if any SSH/SCP command fails
     */
    private function deployFileUsingScp(Host $host, string $localTempFilePath): void
    {
        $remoteSSHPath = '~/.ssh';
        $remoteAuthorizedKeysPath = $remoteSSHPath . '/authorized_keys';

        // Ensure .ssh directory exists with correct permissions (owner only).
        $mkdirProcess = $this->createSshProcess(
            $host,
            ["mkdir -p {$remoteSSHPath} && chmod 700 {$remoteSSHPath}"]
        );
        $mkdirProcess->run();
        if (!$mkdirProcess->isSuccessful()) {
            throw new ProcessFailedException($mkdirProcess);
        }

        // Copy the new authorized_keys file using SCP.
        $scpProcess = $this->createScpProcess($host, $localTempFilePath, $remoteAuthorizedKeysPath);
        $scpProcess->run();
        if (!$scpProcess->isSuccessful()) {
            throw new ProcessFailedException($scpProcess);
        }

        // Set correct permissions on the authorized_keys file (read/write for owner only).
        $chmodProcess = $this->createSshProcess(
            $host,
            ["chmod 600 {$remoteAuthorizedKeysPath}"]
        );
        $chmodProcess->run();
        if (!$chmodProcess->isSuccessful()) {
            throw new ProcessFailedException($chmodProcess);
        }
    }

    /**
     * Creates an SSH Process instance for executing commands on the given host.
     *
     * @param Host               $host     the host to connect to
     * @param array<int, string> $commands an array of commands to execute
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

        $processCommand[] = "{$host->getUsername()}@{$host->getHostname()}";
        $processCommand[] = '-p';
        $processCommand[] = (string) $host->getPort();
        array_push($processCommand, ...$commands); // Spread the actual commands at the end

        return new Process($processCommand);
    }

    /**
     * Creates an SCP Process instance for copying a file to the given host.
     *
     * @param string $localFilePath        path to the local file to copy
     * @param string $remoteTargetFilePath path to the destination on the remote host
     */
    private function createScpProcess(Host $host, string $localFilePath, string $remoteTargetFilePath): Process
    {
        $scpConnectionOptions = array_merge(
            self::CONNECTION_OPTIONS,
            ["ConnectTimeout={$this->connectionTimeout}"] // SCP also uses SSH options
        );

        $processCommand = ['scp'];
        $processCommand[] = '-i';
        $processCommand[] = $this->keyRollPrivateKeyPath;

        foreach ($scpConnectionOptions as $option) {
            $processCommand[] = '-o';
            $processCommand[] = $option;
        }

        $processCommand[] = '-P'; // Note: SCP uses uppercase -P for port
        $processCommand[] = (string) $host->getPort();
        $processCommand[] = $localFilePath;
        $processCommand[] = "{$host->getUsername()}@{$host->getHostname()}:{$remoteTargetFilePath}";

        return new Process($processCommand);
    }
}
