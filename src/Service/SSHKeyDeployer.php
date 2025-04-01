<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Host;
use App\Entity\SSHKey;
use App\Repository\HostRepository;
use App\Repository\SSHKeyRepository;
use Exception;
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
    ];

    private const KEYROLL_MARKER = "\n#################### KeyRoll ####################\n";

    /**
     * Filter for valid SSH key lines.
     */
    private function isValidKey(string $key): bool
    {
        return $key !== '' && !str_starts_with($key, '#');
    }

    /**
     * Process existing keys and extract their signatures.
     *
     * @param array<string> $existingKeysArray
     *
     * @return array<string, string>
     */
    private function processExistingKeys(array $existingKeysArray): array
    {
        $existingKeyParts = [];

        foreach ($existingKeysArray as $key) {
            $parts = preg_split('/\s+/', $key, 3);

            if (count($parts) >= 2) {
                // Use the key type and the actual key as a signature
                $existingKeyParts[$parts[0] . ' ' . $parts[1]] = $key;
                continue;
            }

            // If the format is unexpected, just use the whole key
            $existingKeyParts[$key] = $key;
        }

        return $existingKeyParts;
    }

    /**
     * Add new keys to the collection, avoiding duplicates.
     *
     * @param array<string, string> $existingKeyParts
     * @param array<string>         $newKeysArray
     * @param array<string>         $existingKeysArray
     *
     * @return array<string, string>
     */
    private function addNewKeys(array $existingKeyParts, array $newKeysArray, array $existingKeysArray): array
    {
        foreach ($newKeysArray as $newKey) {
            $parts = preg_split('/\s+/', $newKey, 3);

            if (count($parts) >= 2) {
                $signature = $parts[0] . ' ' . $parts[1];

                if (!isset($existingKeyParts[$signature])) {
                    $existingKeyParts[$signature] = $newKey;
                }

                continue;
            }

            if (!in_array($newKey, $existingKeysArray, true)) {
                $existingKeyParts[$newKey] = $newKey;
            }
        }

        return $existingKeyParts;
    }

    public function __construct(
        private HostRepository $hostRepository,
        private SSHKeyRepository $sshKeyRepository,
        private LoggerInterface $logger,
        private string $keyRollPrivateKeyPath,
        private int $connectionTimeout = 10,
    ) {
    }

    /**
     * Deploys SSH keys to all active hosts.
     */
    public function deployKeys(): void
    {
        // Get all active hosts for deployment
        $hosts = $this->hostRepository->findAll();

        foreach ($hosts as $host) {
            try {
                $this->deployKeysToHost($host);
            } catch (\Exception $e) {
                $this->logger->error('Failed to deploy keys to host {host}: {error}', [
                    'host' => $host->getName(),
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
        }
    }

    /**
     * Deploys SSH keys to a specific host.
     */
    private function deployKeysToHost(Host $host): void
    {
        $this->verifyHostConnection($host);

        // Get Keys
        $keys = $this->sshKeyRepository->findAll();

        if (empty($keys)) {
            $this->logger->info('No keys to deploy to {host}', [
                'host' => $host->getName(),
            ]);

            return;
        }

        // Create an array of public key strings
        $publicKeysToAdd = array_map(fn (SSHKey $key) => $key->getPublicKey(), $keys);

        // Get existing keys from the server
        $existingKeys = $this->getExistingAuthorizedKeys($host);

        // Combine keys, removing duplicates
        $mergedKeys = $this->mergeKeys($existingKeys, $publicKeysToAdd);

        // If nothing changed, don't update the file
        if ($mergedKeys === $existingKeys) {
            $this->logger->info('No changes needed for host {host}, all keys already deployed', [
                'host' => $host->getName(),
            ]);

            return;
        }

        $this->updateAuthorizedKeysFile($host, $mergedKeys);
    }

    /**
     * Updates the authorized_keys file on the remote host.
     */
    private function updateAuthorizedKeysFile(Host $host, string $mergedKeys): void
    {
        // Create a temporary file with the merged keys
        $tempFile = tempnam(sys_get_temp_dir(), 'keyroll_');
        if ($tempFile === false) {
            throw new \RuntimeException('Could not create temporary file for authorized_keys');
        }

        try {
            // Write all merged keys to the temp file
            file_put_contents($tempFile, $mergedKeys);

            // Deploy the merged keys using SCP
            $this->deployAuthorizedKeysFile($host, $tempFile);

            $this->logger->info('Successfully deployed keys to host {host}', [
                'host' => $host->getName(),
                'total_keys' => substr_count($mergedKeys, "\n") + 1,
            ]);
        } finally {
            // Clean up the temp file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Verifies the connection to the remote host.
     *
     * @throws ProcessFailedException If the connection fails
     */
    private function verifyHostConnection(Host $host): void
    {
        $process = $this->createSshProcess(
            $host,
            ['echo "Connection successful"']
        );

        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    /**
     * Gets the existing authorized_keys from the remote host.
     */
    private function getExistingAuthorizedKeys(Host $host): string
    {
        $process = $this->createSshProcess(
            $host,
            ['cat ~/.ssh/authorized_keys 2>/dev/null || echo ""']
        );

        $process->run();

        // We don't throw an exception if the file doesn't exist, as cat will return an empty string
        return trim($process->getOutput());
    }

    /**
     * Merges existing keys with new keys, avoiding duplicates.
     *
     * @param string             $existingKeysString The existing authorized_keys content
     * @param array<int, string> $newKeysArray       Array of new SSH public keys to add
     *
     * @return string The merged authorized_keys content
     */
    private function mergeKeys(string $existingKeysString, array $newKeysArray): string
    {
        // Normalize line endings and split into array
        $existingKeysString = str_replace("\r\n", "\n", $existingKeysString);
        $existingKeysArray = $existingKeysString ? explode("\n", $existingKeysString) : [];

        // Normalize and clean up existing keys
        $existingKeysArray = array_map('trim', $existingKeysArray);
        $existingKeysArray = array_filter($existingKeysArray, $this->isValidKey(...));

        // Normalize and clean up new keys
        $newKeysArray = array_map('trim', $newKeysArray);
        $newKeysArray = array_filter($newKeysArray, $this->isValidKey(...));

        // Process existing keys
        $existingKeyParts = $this->processExistingKeys($existingKeysArray);

        // Add KeyRoll marker
        $existingKeyParts['keyroll_marker'] = self::KEYROLL_MARKER;

        // Add new keys
        $existingKeyParts = $this->addNewKeys($existingKeyParts, $newKeysArray, $existingKeysArray);

        // Convert back to single string
        return implode("\n", array_values($existingKeyParts));
    }

    /**
     * Deploys the authorized_keys file to the remote host.
     *
     * @throws ProcessFailedException If any step fails
     */
    private function deployAuthorizedKeysFile(Host $host, string $tempFile): void
    {
        // Create .ssh directory if it doesn't exist
        $mkdirProcess = $this->createSshProcess(
            $host,
            ['mkdir -p ~/.ssh && chmod 700 ~/.ssh']
        );

        $mkdirProcess->run();

        if (!$mkdirProcess->isSuccessful()) {
            throw new ProcessFailedException($mkdirProcess);
        }

        // Copy the authorized_keys file using SCP
        $scpProcess = $this->createScpProcess($host, $tempFile);
        $scpProcess->run();

        if (!$scpProcess->isSuccessful()) {
            throw new ProcessFailedException($scpProcess);
        }

        // Set correct permissions on the authorized_keys file
        $chmodProcess = $this->createSshProcess(
            $host,
            ['chmod 600 ~/.ssh/authorized_keys']
        );

        $chmodProcess->run();

        if (!$chmodProcess->isSuccessful()) {
            throw new ProcessFailedException($chmodProcess);
        }
    }

    /**
     * Creates an SSH Process for the given host and command.
     *
     * @param Host               $host     The host to connect to
     * @param array<int, string> $commands Commands to execute
     */
    private function createSshProcess(Host $host, array $commands): Process
    {
        $options = array_merge(
            self::CONNECTION_OPTIONS,
            ["ConnectTimeout={$this->connectionTimeout}"]
        );

        $sshOptions = [];
        foreach ($options as $option) {
            $sshOptions[] = '-o';
            $sshOptions[] = $option;
        }

        return new Process([
            'ssh',
            '-i',
            $this->keyRollPrivateKeyPath,
            ...$sshOptions,
            "{$host->getUsername()}@{$host->getHostname()}",
            '-p',
            (string) $host->getPort(),
            ...$commands,
        ]);
    }

    /**
     * Creates an SCP Process for the given host and file.
     */
    private function createScpProcess(Host $host, string $tempFile): Process
    {
        $options = array_merge(
            self::CONNECTION_OPTIONS,
            ["ConnectTimeout={$this->connectionTimeout}"]
        );

        $scpOptions = [];
        foreach ($options as $option) {
            $scpOptions[] = '-o';
            $scpOptions[] = $option;
        }

        return new Process([
            'scp',
            '-i',
            $this->keyRollPrivateKeyPath,
            ...$scpOptions,
            '-P',
            (string) $host->getPort(),
            $tempFile,
            "{$host->getUsername()}@{$host->getHostname()}:~/.ssh/authorized_keys",
        ]);
    }
}
