<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220918161210 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_notification_settings DROP FOREIGN KEY FK_7051D51EA76ED395');
        $this->addSql('DROP TABLE user_notification_settings');
        $this->addSql('ALTER TABLE user ADD notified_on_events TINYINT(1) NOT NULL, ADD notified_on_own_events TINYINT(1) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_notification_settings (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, notified_on_events TINYINT(1) NOT NULL, notified_on_own_events TINYINT(1) NOT NULL, UNIQUE INDEX UNIQ_7051D51EA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE user_notification_settings ADD CONSTRAINT FK_7051D51EA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user DROP notified_on_events, DROP notified_on_own_events');
    }
}
