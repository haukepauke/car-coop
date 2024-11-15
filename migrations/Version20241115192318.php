<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241115192318 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking ADD editor_id INT DEFAULT NULL');
        $this->addSQL('UPDATE booking SET editor_id = user_id');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE6995AC4C FOREIGN KEY (editor_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_E00CEDDE6995AC4C ON booking (editor_id)');
        $this->addSql('ALTER TABLE expense ADD editor_id INT NOT NULL');
        $this->addSQL('UPDATE expense SET editor_id = user_id');
        $this->addSql('ALTER TABLE expense ADD CONSTRAINT FK_2D3A8DA66995AC4C FOREIGN KEY (editor_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_2D3A8DA66995AC4C ON expense (editor_id)');
        $this->addSql('ALTER TABLE trip ADD editor_id INT DEFAULT NULL');
        $this->addSQL('UPDATE trip SET editor_id = user_id');
        $this->addSql('ALTER TABLE trip ADD CONSTRAINT FK_7656F53B6995AC4C FOREIGN KEY (editor_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_7656F53B6995AC4C ON trip (editor_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDE6995AC4C');
        $this->addSql('DROP INDEX IDX_E00CEDDE6995AC4C ON booking');
        $this->addSql('ALTER TABLE booking DROP editor_id');
        $this->addSql('ALTER TABLE expense DROP FOREIGN KEY FK_2D3A8DA66995AC4C');
        $this->addSql('DROP INDEX IDX_2D3A8DA66995AC4C ON expense');
        $this->addSql('ALTER TABLE expense DROP editor_id');
        $this->addSql('ALTER TABLE trip DROP FOREIGN KEY FK_7656F53B6995AC4C');
        $this->addSql('DROP INDEX IDX_7656F53B6995AC4C ON trip');
        $this->addSql('ALTER TABLE trip DROP editor_id');
    }
}
