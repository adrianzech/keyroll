<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250516100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add TOTP secret and enabled flag to user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD totp_secret VARCHAR(255) DEFAULT NULL, ADD is_totp_enabled TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP totp_secret, DROP is_totp_enabled');
    }
}
