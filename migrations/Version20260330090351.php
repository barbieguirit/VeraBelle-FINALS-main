<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260330090351 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE challenge (id INT AUTO_INCREMENT NOT NULL, created_by_id INT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, theme LONGTEXT DEFAULT NULL, start_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', end_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', voting_start_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', voting_end_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', status VARCHAR(50) NOT NULL, max_entries INT DEFAULT NULL, prizes JSON NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_D7098951B03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE entry (id INT AUTO_INCREMENT NOT NULL, challenge_id INT NOT NULL, submitted_by_id INT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, entry_type VARCHAR(50) NOT NULL, files JSON NOT NULL, metadata JSON DEFAULT NULL, vote_count INT NOT NULL, `rank` INT DEFAULT NULL, status VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_2B219D7098A21AC6 (challenge_id), INDEX IDX_2B219D7079F7D87D (submitted_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_badge (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, badge_name VARCHAR(100) NOT NULL, description VARCHAR(255) DEFAULT NULL, challenge_id INT DEFAULT NULL, level VARCHAR(50) NOT NULL, earned_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_1C32B345A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE vote (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, entry_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_5A108564A76ED395 (user_id), INDEX IDX_5A108564BA364942 (entry_id), UNIQUE INDEX unique_user_entry_vote (user_id, entry_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE challenge ADD CONSTRAINT FK_D7098951B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE entry ADD CONSTRAINT FK_2B219D7098A21AC6 FOREIGN KEY (challenge_id) REFERENCES challenge (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE entry ADD CONSTRAINT FK_2B219D7079F7D87D FOREIGN KEY (submitted_by_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_badge ADD CONSTRAINT FK_1C32B345A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vote ADD CONSTRAINT FK_5A108564A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vote ADD CONSTRAINT FK_5A108564BA364942 FOREIGN KEY (entry_id) REFERENCES entry (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE challenge DROP FOREIGN KEY FK_D7098951B03A8386');
        $this->addSql('ALTER TABLE entry DROP FOREIGN KEY FK_2B219D7098A21AC6');
        $this->addSql('ALTER TABLE entry DROP FOREIGN KEY FK_2B219D7079F7D87D');
        $this->addSql('ALTER TABLE user_badge DROP FOREIGN KEY FK_1C32B345A76ED395');
        $this->addSql('ALTER TABLE vote DROP FOREIGN KEY FK_5A108564A76ED395');
        $this->addSql('ALTER TABLE vote DROP FOREIGN KEY FK_5A108564BA364942');
        $this->addSql('DROP TABLE challenge');
        $this->addSql('DROP TABLE entry');
        $this->addSql('DROP TABLE user_badge');
        $this->addSql('DROP TABLE vote');
    }
}
