<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\SSHKey;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class SSHKeyTest extends TestCase
{
    public function testSSHKeyCreation(): void
    {
        $sshKey = new SSHKey();
        $sshKey->setName('My SSH Key');
        $sshKey->setPublicKey('ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIOMqqnkVzrm0SdG6UOoqKLsabgH5C9okWi0dh2l9GKJl user@example.com');

        $this->assertEquals('My SSH Key', $sshKey->getName());
        $this->assertStringStartsWith('ssh-ed25519', $sshKey->getPublicKey());
    }

    public function testUserRelationship(): void
    {
        $user = new User();
        $user->setName('John Doe');
        $user->setEmail('john@example.com');

        $sshKey = new SSHKey();
        $sshKey->setName('Test Key');
        $sshKey->setPublicKey('ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQ...');
        $sshKey->setUser($user);

        $this->assertEquals($user, $sshKey->getUser());
    }

    public function testTimestamps(): void
    {
        $sshKey = new SSHKey();
        $sshKey->updateTimestamps();

        $this->assertInstanceOf(\DateTimeImmutable::class, $sshKey->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $sshKey->getUpdatedAt());
    }

    public function testIdIsNullByDefault(): void
    {
        $sshKey = new SSHKey();
        $this->assertNull($sshKey->getId());
    }

    public function testValidSSHKeyFormats(): void
    {
        $validKeys = [
            'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQ...',
            'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIOMqqnkVzrm0SdG6UOoqKLsabgH5C9okWi0dh2l9GKJl',
            'ecdsa-sha2-nistp256 AAAAE2VjZHNhLXNoYTItbmlzdHAyNTYAAAAIbmlzdHA...',
        ];

        foreach ($validKeys as $key) {
            $sshKey = new SSHKey();
            $sshKey->setPublicKey($key);

            $this->assertEquals($key, $sshKey->getPublicKey());
        }
    }
}
