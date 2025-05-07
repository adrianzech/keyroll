<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250430140420 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE `category` (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_64C19C15E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE host_category (host_id INT NOT NULL, category_id INT NOT NULL, INDEX IDX_57A5E5D71FB8D185 (host_id), INDEX IDX_57A5E5D712469DE2 (category_id), PRIMARY KEY(host_id, category_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_category (user_id INT NOT NULL, category_id INT NOT NULL, INDEX IDX_E6C1FDC1A76ED395 (user_id), INDEX IDX_E6C1FDC112469DE2 (category_id), PRIMARY KEY(user_id, category_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE host_category ADD CONSTRAINT FK_57A5E5D71FB8D185 FOREIGN KEY (host_id) REFERENCES `host` (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE host_category ADD CONSTRAINT FK_57A5E5D712469DE2 FOREIGN KEY (category_id) REFERENCES `category` (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_category ADD CONSTRAINT FK_E6C1FDC1A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_category ADD CONSTRAINT FK_E6C1FDC112469DE2 FOREIGN KEY (category_id) REFERENCES `category` (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE host_category DROP FOREIGN KEY FK_57A5E5D71FB8D185
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE host_category DROP FOREIGN KEY FK_57A5E5D712469DE2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_category DROP FOREIGN KEY FK_E6C1FDC1A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_category DROP FOREIGN KEY FK_E6C1FDC112469DE2
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE `category`
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE host_category
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_category
        SQL);
    }
}
