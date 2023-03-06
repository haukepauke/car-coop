<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220918123420 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_notification_settings ADD user_id INT NOT NULL');
        $this->addSql('ALTER TABLE user_notification_settings ADD CONSTRAINT FK_7051D51EA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7051D51EA76ED395 ON user_notification_settings (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_notification_settings DROP FOREIGN KEY FK_7051D51EA76ED395');
        $this->addSql('DROP INDEX UNIQ_7051D51EA76ED395 ON user_notification_settings');
        $this->addSql('ALTER TABLE user_notification_settings DROP user_id');
    }
}
