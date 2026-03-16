<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260316000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add message board table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE message (id INT AUTO_INCREMENT NOT NULL, car_id INT NOT NULL, author_id INT DEFAULT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', is_sticky TINYINT(1) NOT NULL, INDEX IDX_message_car (car_id), INDEX IDX_message_author (author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_message_car FOREIGN KEY (car_id) REFERENCES car (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_message_author FOREIGN KEY (author_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_message_car');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_message_author');
        $this->addSql('DROP TABLE message');
    }
}
