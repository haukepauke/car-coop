<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220512103317 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE invitation ADD user_type_id INT NOT NULL');
        $this->addSql('ALTER TABLE invitation ADD CONSTRAINT FK_F11D61A29D419299 FOREIGN KEY (user_type_id) REFERENCES user_type (id)');
        $this->addSql('CREATE INDEX IDX_F11D61A29D419299 ON invitation (user_type_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE invitation DROP FOREIGN KEY FK_F11D61A29D419299');
        $this->addSql('DROP INDEX IDX_F11D61A29D419299 ON invitation');
        $this->addSql('ALTER TABLE invitation DROP user_type_id');
    }
}
