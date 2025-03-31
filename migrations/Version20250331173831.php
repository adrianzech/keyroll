<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250331173831 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(
            <<<'SQL'
            CREATE TABLE `host` (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, hostname VARCHAR(255) NOT NULL, port INT NOT NULL, username VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_CF2713FD5E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL,
        );
        $this->addSql(
            <<<'SQL'
            CREATE TABLE `ssh_key` (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, name VARCHAR(255) NOT NULL, public_key VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_82A73B64A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL,
        );
        $this->addSql(
            <<<'SQL'
            ALTER TABLE `ssh_key` ADD CONSTRAINT FK_82A73B64A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)
        SQL,
        );
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(
            <<<'SQL'
            ALTER TABLE `ssh_key` DROP FOREIGN KEY FK_82A73B64A76ED395
        SQL,
        );
        $this->addSql(
            <<<'SQL'
            DROP TABLE `host`
        SQL,
        );
        $this->addSql(
            <<<'SQL'
            DROP TABLE `ssh_key`
        SQL,
        );
    }
}
